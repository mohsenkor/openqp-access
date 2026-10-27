// /download/agreement.js: "Email me a sign-in link" opens the agreement as a pop-up; the
// sign-in email is sent only from its "I Agree and Download" button. Without JavaScript the
// server shows the same agreement after the first click (app.php, ?agreement).
(function () {
  var signin = document.getElementById('signin');
  var dialog = document.getElementById('agreement');
  if (!signin || !dialog || typeof dialog.showModal !== 'function') { return; }
  function open() {
    if (dialog.open) { dialog.close(); }
    dialog.classList.remove('fallback');
    dialog.showModal();
  }
  signin.addEventListener('submit', function (ev) {   // runs after the browser has checked the email
    ev.preventDefault();
    dialog.querySelector('input[name=email]').value = signin.elements.email.value;
    open();
  });
  if (dialog.open) { open(); }
})();
