# OpenQP access

A sign-up page for OpenQP downloads, served by GitHub Pages:
<https://mohsenkor.github.io/openqp-access/>

1. A person enters their email and receives a one-time sign-in link (no password).
2. After signing in, the page shows the email it detected and asks for name,
   institution, planned use, and acceptance of the OpenQP Research License.
3. The details are saved. A university email (`.ac.kr`, `.edu`, `.ac.uk`, …,
   see `supabase/setup.sql`) is approved at once; any other email waits for a
   manual review.
4. Approved people see the files and get a download link for each, valid for a
   few minutes. They can also have a sign-in link emailed to them that brings
   them back to their downloads.

The page is plain HTML and JavaScript. Sign-in and the saved details live in a
[Supabase](https://supabase.com) project; its database rules (in
`supabase/setup.sql`) decide who can read what, so the public values in
`config.js` are safe to publish. The files themselves stay in a **private GitHub
release**. The Supabase function `download` (in `supabase/functions/download/`)
checks that the person is approved and asks GitHub for a short-lived link to the
file they chose; the GitHub token stays inside Supabase.

The function offers only the four platform archives and `SHA256SUMS` of the
release (`openqp-<version>-{linux-x86_64,macos-arm64,macos-x86_64,windows-x86_64}`);
wheels, Linux ARM64 and other files in the release are not offered.

## Setting it up

1. **Create a Supabase project** (free) at <https://supabase.com>.
2. **Run the setup script.** In the project: *SQL Editor* → *New query* → paste
   all of [`supabase/setup.sql`](supabase/setup.sql) → *Run*.
3. **Allow the page's address.** *Authentication* → *URL Configuration*: set
   *Site URL* to `https://mohsenkor.github.io/openqp-access/` and add the same
   address under *Redirect URLs*.
4. **Connect the page.** *Project Settings* → *API Keys*: copy the *Project URL*
   and the *publishable* key into `config.js`.
5. **Create a read-only GitHub token** for the private files repository: GitHub →
   *Settings* → *Developer settings* → *Fine-grained tokens* → *Generate new token*.
   Repository access: *Only select repositories* → the files repository.
   Permissions → *Repository* → *Contents*: **Read-only**. Nothing else.
6. **Store the secrets.** Supabase → *Edge Functions* → *Secrets*: add
   `GITHUB_TOKEN` (the token) and `GITHUB_REPO` (for example
   `mohsenkor/openqp_release`). Optional: `GITHUB_TAG` to pin a release instead
   of the latest one.
7. **Deploy the function.** *Edge Functions* → *Deploy a new function* → *Via
   Editor*, name it `download`, paste `supabase/functions/download/index.ts`, and
   deploy. In the function's settings, turn **off** "Verify JWT": the function
   checks the sign-in itself with the Auth server.

The `binaries` storage bucket created by `setup.sql` is not used for downloads.

Optional: to offer "Sign in with GitHub", enable the GitHub provider under
*Authentication* → *Providers* (it needs a GitHub OAuth App) and set
`enableGitHub: true` in `config.js`.

## Reviewing requests

*Table Editor* → `access_requests`. Rows with status `pending` are people whose
email is not on the university list. Change `status` to `approved` or
`declined`; the person sees the result the next time they sign in.
To approve a whole domain automatically, add it to the `academic_domains` table.

## Limits of the free setup

- Supabase's built-in email sender is for testing: it sends only a few emails
  per hour, and only to members of your Supabase team. Before real users sign
  up, connect an email service under *Authentication* → *Emails* → *SMTP
  Settings*.
- A free Supabase project pauses after a week without activity; restore it
  from the dashboard if the page stops signing people in.
