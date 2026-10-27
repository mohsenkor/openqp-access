(function () {
  "use strict";

  var cfg = window.OQP_ACCESS_CONFIG || {};
  var view = document.getElementById("view");
  var who = document.getElementById("who");
  var siteUrl = location.origin + location.pathname;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function show(html) { view.innerHTML = html; }
  function message(kind, text) {
    var box = document.getElementById("msg");
    if (!box) return;
    box.className = "msg " + kind;
    box.textContent = text;
    box.hidden = !text;
  }

  if (!cfg.supabaseUrl || !cfg.supabaseAnonKey || !window.supabase) {
    show('<div class="card"><h2>Setup needed</h2><p>This page is not connected to its Supabase project yet. ' +
         'Fill in <code>config.js</code> as described in the README.</p></div>');
    return;
  }

  var db = window.supabase.createClient(cfg.supabaseUrl, cfg.supabaseAnonKey);

  // ---- signed out: ask for an email, send a one-time sign-in link ----------
  function renderSignIn() {
    who.textContent = "";
    show(
      '<div class="card"><h2>Get OpenQP</h2>' +
      "<p>OpenQP is free for academic and nonprofit research and teaching. Sign in with your university email: " +
      "we email you a one-time link, there is no password. University addresses get access right away; " +
      "other addresses are reviewed by hand.</p>" +
      '<form id="signin" class="stack" novalidate>' +
      '<label for="email">Email</label>' +
      '<input id="email" name="email" type="email" autocomplete="email" required placeholder="name@university.ac.kr">' +
      '<p id="hint" class="hint" hidden></p>' +
      '<button class="primary" type="submit">Email me a sign-in link</button>' +
      "</form>" +
      (cfg.enableGitHub ? '<div class="or">or</div><button id="gh" type="button">Sign in with GitHub</button>' : "") +
      '<p id="msg" class="msg" hidden></p></div>'
    );
    var email = document.getElementById("email");
    var hint = document.getElementById("hint");
    var timer = null;
    email.addEventListener("input", function () {
      clearTimeout(timer);
      var value = email.value.trim();
      if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(value)) { hint.hidden = true; return; }
      timer = setTimeout(function () {
        db.rpc("is_academic", { addr: value }).then(function (r) {
          if (r.error) { hint.hidden = true; return; }
          hint.hidden = false;
          hint.className = "hint " + (r.data ? "ok" : "warn");
          hint.textContent = r.data
            ? "University address recognised: you get access right after signing in."
            : "This address is not on the university list: your request will be reviewed by hand.";
        });
      }, 350);
    });
    document.getElementById("signin").addEventListener("submit", function (ev) {
      ev.preventDefault();
      var value = email.value.trim();
      if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(value)) { message("bad", "Enter a valid email address."); return; }
      var button = ev.target.querySelector("button");
      button.disabled = true;
      db.auth.signInWithOtp({ email: value, options: { emailRedirectTo: siteUrl } }).then(function (r) {
        button.disabled = false;
        if (r.error) { message("bad", "Could not send the link: " + r.error.message); return; }
        message("ok", "Check your inbox at " + value + " and open the link in it. You can close this page.");
      });
    });
    var gh = document.getElementById("gh");
    if (gh) gh.addEventListener("click", function () {
      db.auth.signInWithOAuth({ provider: "github", options: { redirectTo: siteUrl } });
    });
  }

  // ---- signed in: find this person's request --------------------------------
  function renderSignedIn(user) {
    who.innerHTML = 'Signed in as <strong>' + esc(user.email) + '</strong> · <button id="signout" type="button" class="link">Sign out</button>';
    document.getElementById("signout").addEventListener("click", function () {
      db.auth.signOut().then(function () { location.hash = ""; renderSignIn(); });
    });
    show('<div class="card"><p>Loading…</p></div>');
    db.from("access_requests").select("*").eq("user_id", user.id).maybeSingle().then(function (r) {
      if (r.error) { show('<div class="card"><h2>Something went wrong</h2><p>' + esc(r.error.message) + "</p></div>"); return; }
      if (!r.data) renderForm(user);
      else if (r.data.status === "approved") renderDownloads(user, r.data);
      else renderWaiting(r.data);
    });
  }

  function renderForm(user) {
    db.rpc("is_academic", { addr: user.email }).then(function (r) {
      var academic = !r.error && r.data;
      show(
        '<div class="card"><h2>Your details</h2>' +
        "<p>We detected your email <strong>" + esc(user.email) + "</strong>. " +
        (academic ? "It is a university address, so you get access as soon as you submit this form."
                  : "It is not on the university list, so your request will be reviewed by hand.") + "</p>" +
        '<form id="details" class="stack" novalidate>' +
        '<label for="name">Full name</label><input id="name" required maxlength="200" autocomplete="name">' +
        '<label for="inst">Institution</label><input id="inst" required maxlength="300" autocomplete="organization">' +
        '<label for="purpose">Planned research use (optional)</label><textarea id="purpose" rows="3" maxlength="2000"></textarea>' +
        '<label class="check"><input id="c-acad" type="checkbox"> I will use OpenQP only for non-commercial academic or nonprofit research or teaching.</label>' +
        '<label class="check"><input id="c-lic" type="checkbox"> I have read and accept the <a href="' + esc(cfg.licenceUrl) + '" target="_blank" rel="noopener">OpenQP Research License 1.0</a>.</label>' +
        '<button class="primary" type="submit">Submit</button>' +
        '</form><p id="msg" class="msg" hidden></p>' +
        '<p class="fine">We store your email, name, institution, the use you describe, the licence version and the date. Nothing else.</p></div>'
      );
      document.getElementById("details").addEventListener("submit", function (ev) {
        ev.preventDefault();
        var name = document.getElementById("name").value.trim();
        var inst = document.getElementById("inst").value.trim();
        var purpose = document.getElementById("purpose").value.trim();
        if (!name || !inst) { message("bad", "Enter your name and institution."); return; }
        if (!document.getElementById("c-acad").checked || !document.getElementById("c-lic").checked) {
          message("bad", "Tick both boxes to accept the licence terms."); return;
        }
        var button = ev.target.querySelector("button");
        button.disabled = true;
        db.from("access_requests")
          .insert({ user_id: user.id, email: user.email, full_name: name, institution: inst, purpose: purpose || null })
          .select().single()
          .then(function (res) {
            button.disabled = false;
            if (res.error) { message("bad", "Could not save your details: " + res.error.message); return; }
            if (res.data.status === "approved") renderDownloads(user, res.data);
            else renderWaiting(res.data);
          });
      });
    });
  }

  function renderWaiting(req) {
    var declined = req.status === "declined";
    show(
      '<div class="card"><h2>' + (declined ? "Request declined" : "Request received") + "</h2>" +
      (declined
        ? "<p>Your request for " + esc(req.email) + " was not approved. Write to the contact in Section 13 of the licence if you think this is a mistake.</p>"
        : "<p>Thank you, " + esc(req.full_name) + ". Your email " + esc(req.email) + " is not on the university list, so the request is reviewed by hand. " +
          "When it is approved, sign in here again to download.</p>") +
      "</div>"
    );
  }

  // ---- approved: list the files and hand out short-lived links ---------------
  // The files are in a private GitHub release. The "download" function checks
  // that this person is approved and asks GitHub for a link to one file; that
  // link works for a few minutes and needs no GitHub account.
  function callDownload(body) {
    return db.functions.invoke("download", { body: body }).then(function (r) {
      if (r.error) {
        var ctx = r.error.context;
        if (ctx && typeof ctx.json === "function") {
          return ctx.json().then(function (j) { throw new Error(j.error || r.error.message); },
                                 function () { throw new Error(r.error.message); });
        }
        throw new Error(r.error.message);
      }
      return r.data;
    });
  }

  function renderDownloads(user, req) {
    show(
      '<div class="card"><h2>Downloads</h2>' +
      '<p>Access approved for ' + esc(req.email) + '. Click "Get download link" next to a file; the link works for a few minutes.</p>' +
      '<div id="files" class="files"><p>Loading files…</p></div>' +
      '<div class="row"><button id="mail" type="button">Email me a link to this page</button></div>' +
      '<p id="msg" class="msg" hidden></p></div>'
    );
    document.getElementById("mail").addEventListener("click", function () {
      db.auth.signInWithOtp({ email: user.email, options: { emailRedirectTo: siteUrl, shouldCreateUser: false } })
        .then(function (r) {
          if (r.error) message("bad", "Could not send the email: " + r.error.message);
          else message("ok", "Sent to " + user.email + ". The link in it signs you in and opens your downloads.");
        });
    });
    callDownload({ action: "list" }).then(function (data) {
      var box = document.getElementById("files");
      var files = (data && data.files) || [];
      if (!files.length) { box.innerHTML = "<p>No files are available yet.</p>"; return; }
      box.innerHTML = '<p class="fine">OpenQP ' + esc(String(data.release || "").replace(/^v/, "")) + '</p>' +
        files.map(function (f, i) {
          var size = f.size ? (f.size / 1048576).toFixed(1) + " MB" : "";
          return '<div class="file"><span class="name">' + esc(f.name) + '</span><span class="size">' + size +
                 '</span><button type="button" data-i="' + i + '">Get download link</button></div>';
        }).join("");
      box.querySelectorAll("button[data-i]").forEach(function (b) {
        b.addEventListener("click", function () {
          var f = files[+b.getAttribute("data-i")];
          b.disabled = true;
          callDownload({ action: "link", asset_id: f.id }).then(function (d) {
            var a = document.createElement("a");
            a.href = d.url; a.textContent = "Download " + f.name; a.className = "dl";
            a.rel = "noopener";
            b.replaceWith(a);
          }, function (e) {
            b.disabled = false;
            message("bad", "Could not create the link: " + e.message);
          });
        });
      });
    }, function (e) {
      document.getElementById("files").innerHTML = "<p>Could not list the files: " + esc(e.message) + "</p>";
    });
  }

  // ---- start -----------------------------------------------------------------
  var current = null;
  function route(session) {
    var user = session && session.user;
    if (user && current === user.id) return;
    current = user ? user.id : null;
    if (user) renderSignedIn(user); else renderSignIn();
  }
  db.auth.getSession().then(function (r) { route(r.data && r.data.session); });
  db.auth.onAuthStateChange(function (_event, session) { route(session); });
})();
