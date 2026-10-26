# OpenQP access

A sign-up page for OpenQP downloads, served by GitHub Pages:
<https://mohsenkor.github.io/openqp-access/>

1. A person enters their email and receives a one-time sign-in link (no password).
2. After signing in, the page shows the email it detected and asks for name,
   institution, planned use, and acceptance of the OpenQP Research License.
3. The details are saved. A university email (`.ac.kr`, `.edu`, `.ac.uk`, …,
   see `supabase/setup.sql`) is approved at once; any other email waits for a
   manual review.
4. Approved people see the files and get download links that expire after 60
   minutes. They can also have a sign-in link emailed to them that brings them
   back to their downloads.

The page is plain HTML and JavaScript. Sign-in, the saved details and the
private files live in a [Supabase](https://supabase.com) project. Its database
rules (in `supabase/setup.sql`) decide who can read what, so the public values
in `config.js` are safe to publish.

## Setting it up

1. **Create a Supabase project** (free) at <https://supabase.com>.
2. **Run the setup script.** In the project: *SQL Editor* → *New query* → paste
   all of [`supabase/setup.sql`](supabase/setup.sql) → *Run*.
3. **Allow the page's address.** *Authentication* → *URL Configuration*: set
   *Site URL* to `https://mohsenkor.github.io/openqp-access/` and add the same
   address under *Redirect URLs*.
4. **Upload a file to test with.** *Storage* → bucket `binaries` → *Upload file*.
   Use a small test file first, not the OpenQP binaries.
5. **Connect the page.** *Project Settings* → *API*: copy the *Project URL* and
   the *anon public* key into `config.js`.

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
- The free plan has storage and bandwidth limits; check them against the size
  of the files before uploading releases.
