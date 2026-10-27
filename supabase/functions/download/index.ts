// Supabase Edge Function "download": hands approved people short-lived links
// to the OpenQP archives in a private GitHub release.
//
// POST {"action": "list"}                  -> {release, files: [{id, name, size}]}
// POST {"action": "link", "asset_id": 123} -> {url}  (GitHub signed URL, valid for a few minutes)
//
// Secrets (Edge Functions -> Secrets):
//   GITHUB_TOKEN   fine-grained token with read-only "Contents" on the files repository
//   GITHUB_REPO    owner/name of the private repository, for example "mohsenkor/openqp_release"
//   GITHUB_TAG     optional release tag; the latest release when unset
//   ASSET_PATTERN  optional regular expression for the file names offered
// SUPABASE_URL and SUPABASE_ANON_KEY are provided by Supabase.
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.4";

const DEFAULT_PATTERN =
  "^(openqp-[0-9][0-9.]*-(linux-x86_64|macos-arm64|macos-x86_64|windows-x86_64)\\.(tar\\.gz|zip)|SHA256SUMS)$";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

function reply(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...cors, "Content-Type": "application/json" },
  });
}

async function github(path: string, accept = "application/vnd.github+json", redirect: RequestRedirect = "follow") {
  return await fetch(`https://api.github.com/repos/${Deno.env.get("GITHUB_REPO")}${path}`, {
    headers: {
      Authorization: `Bearer ${Deno.env.get("GITHUB_TOKEN")}`,
      Accept: accept,
      "X-GitHub-Api-Version": "2022-11-28",
      "User-Agent": "openqp-access",
    },
    redirect,
  });
}

async function offeredFiles() {
  const tag = Deno.env.get("GITHUB_TAG");
  const res = await github(tag ? `/releases/tags/${encodeURIComponent(tag)}` : "/releases/latest");
  if (!res.ok) throw new Error(`GitHub release lookup failed (${res.status})`);
  const release = await res.json();
  const pattern = new RegExp(Deno.env.get("ASSET_PATTERN") || DEFAULT_PATTERN);
  const files = (release.assets || [])
    .filter((a: { name: string }) => pattern.test(a.name))
    .map((a: { id: number; name: string; size: number }) => ({ id: a.id, name: a.name, size: a.size }));
  return { release: release.tag_name as string, files };
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  if (req.method !== "POST") return reply(405, { error: "use POST" });
  if (!Deno.env.get("GITHUB_TOKEN") || !Deno.env.get("GITHUB_REPO")) {
    return reply(500, { error: "the download service is not configured (GITHUB_TOKEN, GITHUB_REPO)" });
  }

  // Who is asking? The Auth server checks the session token itself.
  const authorization = req.headers.get("Authorization") || "";
  const db = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_ANON_KEY")!, {
    global: { headers: { Authorization: authorization } },
  });
  const token = authorization.replace(/^Bearer\s+/i, "");
  const { data: auth, error: authError } = await db.auth.getUser(token);
  if (authError || !auth?.user) return reply(401, { error: "sign in first" });

  // Approved? Read with the person's own session, so the table's rules apply.
  const { data: request, error: dbError } = await db
    .from("access_requests").select("status").eq("user_id", auth.user.id).maybeSingle();
  if (dbError) return reply(500, { error: dbError.message });
  if (!request || request.status !== "approved") return reply(403, { error: "access not approved" });

  let body: { action?: string; asset_id?: number } = {};
  try { body = await req.json(); } catch { /* empty body means "list" */ }

  try {
    const offered = await offeredFiles();
    if (!body.action || body.action === "list") return reply(200, offered);

    if (body.action === "link") {
      const asset = offered.files.find((f: { id: number }) => f.id === Number(body.asset_id));
      if (!asset) return reply(404, { error: "no such file in this release" });
      const res = await github(`/releases/assets/${asset.id}`, "application/octet-stream", "manual");
      const url = res.headers.get("location");
      if (res.status < 300 || res.status >= 400 || !url) {
        return reply(502, { error: `GitHub did not return a download link (${res.status})` });
      }
      return reply(200, { url, name: asset.name });
    }
    return reply(400, { error: "unknown action" });
  } catch (e) {
    return reply(502, { error: (e as Error).message });
  }
});
