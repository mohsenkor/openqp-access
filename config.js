// Public settings of the Supabase project (Project Settings -> API).
// Both values are meant to be public: the database rules in
// supabase/setup.sql decide what each visitor may read or write.
window.OQP_ACCESS_CONFIG = {
  supabaseUrl: "",       // for example "https://abcdefghijklmnop.supabase.co"
  supabaseAnonKey: "",   // the "anon public" key
  // Show "Sign in with GitHub" too. Turn on only after enabling the GitHub
  // provider under Authentication -> Providers in Supabase.
  enableGitHub: false,
  licenceUrl: "https://github.com/Open-Quantum-Platform/openqp-releases/blob/main/LICENSE",
  downloadLinkMinutes: 60
};
