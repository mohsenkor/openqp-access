<?php /* Page frame in the qchem.knu.ac.kr layout; $body and $who come from app.php. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Download OpenQP: free for academic and nonprofit research and teaching.">
<title>Download OpenQP</title>
<link rel="icon" href="/images/old-site/normal.png">
<link rel="stylesheet" href="/_astro/BaseLayout.DN7PpH-S.css">
<style>
.nav-links a[aria-current=page] { border-color: var(--teal); }
.oqp-access { max-width: 760px; display: grid; gap: 14px; }
.oqp-access .who { color: var(--muted); font-size: .95rem; min-height: 1.4em; margin: 0; }
.oqp-access .card { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 24px; display: grid; gap: 14px; }
.oqp-access h2 { margin: 0; font-size: 1.5rem; }
.oqp-access h3 { margin: 6px 0 0; font-size: 1.1rem; }
.oqp-access p { margin: 0; }
.oqp-access .stack { display: grid; gap: 8px; }
.oqp-access label { font-weight: 650; font-size: .95rem; }
.oqp-access input[type=email], .oqp-access input:not([type]), .oqp-access textarea {
  font: inherit; color: var(--ink); background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 10px 12px; width: 100%; box-sizing: border-box; }
.oqp-access .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
.oqp-access .check input { margin-top: 5px; }
.oqp-access button { font: inherit; min-height: 44px; padding: 10px 16px; border-radius: 6px; border: 1px solid var(--line); background: #fff; color: var(--ink); cursor: pointer; font-weight: 650; }
.oqp-access button.primary { background: var(--blue); border-color: var(--blue); color: #fff; font-weight: 750; }
.oqp-access button.link { min-height: 0; border: 0; padding: 0; background: none; color: var(--blue); text-decoration: underline; font-weight: 400; }
.oqp-access button:focus-visible, .oqp-access input:focus-visible, .oqp-access textarea:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
.oqp-access .msg { font-size: .95rem; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--line); background: #fff; }
.oqp-access .msg.ok { color: var(--green); }
.oqp-access .msg.bad { color: var(--coral); }
.oqp-access .fine { color: var(--muted); font-size: .88rem; }
.oqp-access .files { display: grid; gap: 8px; }
.oqp-access .file { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; justify-content: space-between; padding: 10px 12px; border: 1px solid var(--line); border-radius: 6px; background: #fff; margin: 0; }
.oqp-access .file .name { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9rem; overflow-wrap: anywhere; }
.oqp-access .file .size { color: var(--muted); font-size: .88rem; }
.oqp-access .row { display: flex; flex-wrap: wrap; gap: 10px; }
.oqp-access .inline { display: inline; }
.oqp-access .review { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; padding: 12px; border: 1px solid var(--line); border-radius: 6px; background: #fff; }
.oqp-access .table { overflow-x: auto; }
.oqp-access table { border-collapse: collapse; width: 100%; font-size: .9rem; }
.oqp-access th, .oqp-access td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
.oqp-access dialog.agreement { width: min(760px, calc(100vw - 32px)); max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px);
  padding: 0; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); color: var(--ink); box-sizing: border-box; }
.oqp-access dialog.agreement::backdrop { background: rgba(22, 32, 42, .55); }
.oqp-access dialog.agreement.fallback { position: fixed; inset: 0; margin: auto; z-index: 100; box-shadow: 0 0 0 100vmax rgba(22, 32, 42, .55); }
.oqp-access dialog.agreement[open] { display: flex; flex-direction: column; overflow: hidden; }
.oqp-access dialog.agreement form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
.oqp-access .agreement-head { flex: none; padding: 18px 24px 14px; border-bottom: 1px solid var(--line); }
.oqp-access .agreement-head h2 { font-size: 1.25rem; }
.oqp-access .agreement-text { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 16px 24px 20px; display: grid; gap: 10px; line-height: 1.55; }
.oqp-access .agreement-text:focus-visible { outline: 2px solid var(--blue); outline-offset: -2px; }
.oqp-access .agreement-foot { flex: none; padding: 14px 24px 18px; border-top: 1px solid var(--line); display: grid; gap: 10px; }
.oqp-access .agreement-foot h3 { margin: 0; }
@media (max-width: 560px) {
  .oqp-access .agreement-head, .oqp-access .agreement-text, .oqp-access .agreement-foot { padding-left: 16px; padding-right: 16px; }
  .oqp-access .agreement-foot .row button { flex: 1 1 100%; }
}
</style>
</head>
<body>
<header class="site-header">
  <a class="brand" href="/" aria-label="OpenQP home"><span class="brand-mark">OQ</span><span><strong>OpenQP</strong><small>Open Quantum Platform</small></span></a>
  <nav class="nav-links" aria-label="Main navigation">
    <a href="/openqp/">OpenQP</a>
    <a href="/download/" aria-current="page">Download</a>
    <a href="/openqp/development/">Development</a>
    <a href="/company/">Company</a>
    <a href="/lab/">Lab</a>
    <a href="/research/">Research</a>
    <a href="/people/">People</a>
    <a href="/publications/">Publications</a>
    <a href="/news/">News</a>
    <a href="/contact/">Contact</a>
    <a href="https://app.openqp.org/">Try OpenQP Web</a>
  </nav>
</header>
<main>
  <section class="page-hero">
    <p class="eyebrow">Download</p>
    <h1>Download OpenQP</h1>
    <p>OpenQP is free for academic and nonprofit research and teaching. Sign in with your university email to get the ready-to-run programs for Linux, macOS and Windows.</p>
  </section>
  <section class="band">
    <div class="oqp-access">
      <p class="who"><?= $who ?></p>
      <?= $body ?>
      <p class="fine">Use by or for a company needs a separate written commercial licence from Open Quantum Inc.; see Section 13 of the <a href="/download/LICENSE.txt">OpenQP Research License 1.0</a>.</p>
    </div>
  </section>
</main>
<footer class="site-footer">
  <div><strong>Open Quantum Platform</strong><p>Open-source quantum chemistry, company-backed development, and research from KNU.</p></div>
  <div class="footer-links">
    <a href="/download/">Download OpenQP</a>
    <a href="https://openqp.readthedocs.io/">Documentation</a>
    <a href="mailto:cheolho.choi@gmail.com">Contact</a>
  </div>
</footer>
</body>
</html>
