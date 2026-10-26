-- OpenQP access: one-time setup for a Supabase project.
-- Run the whole file once in the Supabase dashboard: SQL Editor -> New query -> Run.
--
-- What it creates:
--   academic_domains  email domains that are approved automatically
--   access_requests   one row per person: who they are, licence acceptance, status
--   binaries          a private storage bucket for the download files
-- A person who signs in with a confirmed email and submits the form gets
-- status 'approved' if the email domain is academic, otherwise 'pending'.
-- Only approved people can list the files and create download links.

-- 1. Academic email domains ------------------------------------------------
-- An entry matches the domain itself and every subdomain:
-- 'ac.kr' matches knu.ac.kr and chem.knu.ac.kr; 'ethz.ch' matches ethz.ch.
create table if not exists public.academic_domains (
  domain text primary key check (domain = lower(domain) and domain !~ '[@%_\s]'),
  note   text
);
alter table public.academic_domains enable row level security;   -- no policies: only the functions below read it

insert into public.academic_domains (domain, note) values
  ('ac.kr',             'Korean universities'),
  ('edu',               'US universities'),
  ('ac.uk',             'UK universities'),
  ('ac.jp',             'Japanese universities'),
  ('edu.cn',            'Chinese universities'),
  ('ac.in',             'Indian universities'),
  ('edu.au',            'Australian universities'),
  ('ethz.ch',           'ETH Zurich'),
  ('epfl.ch',           'EPFL'),
  ('tum.de',            'Technical University of Munich'),
  ('uni-heidelberg.de', 'Heidelberg University'),
  ('kisti.re.kr',       'KISTI (research institute)')
on conflict (domain) do nothing;

create or replace function public.is_academic(addr text)
returns boolean
language sql stable security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.academic_domains d
    where lower(split_part(addr, '@', 2)) = d.domain
       or right(lower(split_part(addr, '@', 2)), length(d.domain) + 1) = '.' || d.domain
  );
$$;
grant execute on function public.is_academic(text) to anon, authenticated;

-- 2. Access requests -----------------------------------------------------
create table if not exists public.access_requests (
  user_id             uuid primary key references auth.users (id) on delete cascade,
  email               text not null,
  full_name           text not null check (char_length(full_name) between 1 and 200),
  institution         text not null check (char_length(institution) between 1 and 300),
  purpose             text check (char_length(purpose) <= 2000),
  licence_version     text not null default 'OpenQP Research License 1.0',
  licence_accepted_at timestamptz not null default now(),
  status              text not null default 'pending' check (status in ('approved', 'pending', 'declined')),
  created_at          timestamptz not null default now(),
  decided_at          timestamptz
);
alter table public.access_requests enable row level security;

-- The server, not the browser, decides who the request belongs to, which
-- email it is for, when the licence was accepted, and the status.
create or replace function public.access_requests_fill_in()
returns trigger
language plpgsql security definer
set search_path = public
as $$
declare
  confirmed_email text;
begin
  select u.email into confirmed_email
  from auth.users u
  where u.id = auth.uid() and u.email_confirmed_at is not null;
  if confirmed_email is null then
    raise exception 'sign in with a confirmed email first';
  end if;
  new.user_id             := auth.uid();
  new.email               := lower(confirmed_email);
  new.licence_version     := 'OpenQP Research License 1.0';
  new.licence_accepted_at := now();
  new.created_at          := now();
  if public.is_academic(confirmed_email) then
    new.status := 'approved';
    new.decided_at := now();
  else
    new.status := 'pending';
    new.decided_at := null;
  end if;
  return new;
end;
$$;

drop trigger if exists access_requests_fill_in on public.access_requests;
create trigger access_requests_fill_in
  before insert on public.access_requests
  for each row execute function public.access_requests_fill_in();

drop policy if exists "read own request" on public.access_requests;
create policy "read own request" on public.access_requests
  for select to authenticated using (user_id = auth.uid());

drop policy if exists "create own request" on public.access_requests;
create policy "create own request" on public.access_requests
  for insert to authenticated with check (user_id = auth.uid());
-- No update or delete policy: people cannot change their own status. The
-- professor reviews 'pending' rows in Table Editor and sets status there.

-- 3. Private file bucket ---------------------------------------------------
insert into storage.buckets (id, name, public)
values ('binaries', 'binaries', false)
on conflict (id) do nothing;

drop policy if exists "approved users read binaries" on storage.objects;
create policy "approved users read binaries" on storage.objects
  for select to authenticated
  using (
    bucket_id = 'binaries'
    and exists (
      select 1 from public.access_requests r
      where r.user_id = auth.uid() and r.status = 'approved'
    )
  );
