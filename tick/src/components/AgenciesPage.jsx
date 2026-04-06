import React, { useState } from 'react';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';

const FEATURES = [
  {
    icon: '📄',
    title: 'White-labelled PDF Certificates',
    desc: 'Every policy certificate is issued under your agency brand with your logo and contact details.',
  },
  {
    icon: '📊',
    title: 'Real-time Commission Dashboard',
    desc: 'Track every policy sold, commission earned, and payout status — updated live, no reconciliation needed.',
  },
  {
    icon: '👥',
    title: 'Sub-agent Accounts & Role Controls',
    desc: 'Onboard your whole team. Assign roles and limits. Every agent works inside your agency umbrella.',
  },
  {
    icon: '🔌',
    title: 'API Access for Integrations',
    desc: 'Connect Maljani directly to your booking platform, CRM, or POS system via documented REST APIs.',
  },
  {
    icon: '⚡',
    title: 'Bulk Policy Issuance',
    desc: 'Issue dozens of policies to a travel group in a single upload. CSV/Excel support included.',
  },
  {
    icon: '🔔',
    title: 'Renewal & Expiry Alerts',
    desc: 'Automated alerts to you and your clients before a policy expires — never miss a renewal opportunity.',
  },
];

const STEPS = [
  { n: '01', title: 'Apply Online', desc: 'Submit your agency details through our portal. Approval usually takes 1–2 business days.' },
  { n: '02', title: 'Get Onboarded', desc: 'Our team configures your branded workspace, uploads your logo, and adds your commission tiers.' },
  { n: '03', title: 'Start Issuing', desc: 'Log in, pick a policy, fill client details, and issue a PDF certificate in under 3 minutes.' },
];

const AgenciesPage = ({ onNavigate }) => {
  const { user, role } = useAuth();
  const { mobile, tablet } = useResponsive();
  const isLoggedIn = !!user;
  const isAgent = role === 'agent' || role === 'administrator';
  const today = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0];

  return (
    <div style={{ paddingTop: 90 }}>
      <style>{`
        @keyframes float-agency { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        @keyframes dash-spin { to { transform: translateY(-50%) rotate(360deg); } }
        .agency-feature-card:hover { border-color: rgba(49,99,49,0.6) !important; background: rgba(49,99,49,0.1) !important; transform: translateY(-3px); }
        .agency-feature-card { transition: all 0.25s ease; }
      `}</style>

      {/* ── Hero ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '80px 0 96px', overflow: 'hidden' }}>
        <div style={{
          position: 'absolute', right: '-6%', top: '50%', transform: 'translateY(-50%)',
          width: mobile ? 300 : 600, height: mobile ? 300 : 600, border: '1px dashed rgba(49,99,49,0.15)', borderRadius: '50%',
          pointerEvents: 'none', animation: 'dash-spin 90s linear infinite',
        }} aria-hidden="true" />
        <div style={{
          position: 'absolute', right: '6%', top: '50%', transform: 'translateY(-50%)',
          width: mobile ? 180 : 360, height: mobile ? 180 : 360, border: '1px dashed rgba(246,166,35,0.1)', borderRadius: '50%',
          pointerEvents: 'none', animation: 'dash-spin 60s linear infinite reverse',
        }} aria-hidden="true" />

        <div className="container">
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? '1fr 340px' : '1fr 420px', gap: mobile ? 40 : 72, alignItems: 'center' }}>
            {/* Left */}
            <div>
              <p className="section-label">For Insurance Agencies</p>
              <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(40px,4.5vw,62px)', fontWeight: 800, lineHeight: 1.05, letterSpacing: '-0.025em', marginBottom: 20 }}>
                Issue Policies in Bulk.<br />
                <em style={{ fontStyle: 'normal', background: 'linear-gradient(135deg,var(--gold),var(--gold-light))', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', backgroundClip: 'text' }}>From One Dashboard.</em>
              </h1>
              <p style={{ color: 'var(--slate)', fontSize: 17, lineHeight: 1.8, marginBottom: 32, maxWidth: 520 }}>
                Join 200+ travel agencies already issuing instant certificates through Maljani.
                Your brand. Your commissions. Full control.
              </p>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 36 }}>
                {['\u2713 Zero upfront cost — commission-based model', '\u2713 Branded PDF certificates in under 3 minutes', '\u2713 15+ insurer partners, single integration', '\u2713 Dedicated onboarding support'].map(f => (
                  <div key={f} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 15 }}>
                    <span style={{ color: '#22c55e' }}>{f[0]}</span>
                    <span style={{ color: 'rgba(255,255,255,0.82)' }}>{f.slice(1)}</span>
                  </div>
                ))}
              </div>
              <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap' }}>
                {isLoggedIn && isAgent ? (
                  <>
                    <button className="btn btn--primary btn--lg" onClick={() => onNavigate('dashboard')}>Open Agency Dashboard &rarr;</button>
                    <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('wizard', null, { region: '', departure: today, returnDate: nextWeek, passengers: 1 })}>New Quote</button>
                  </>
                ) : (
                  <>
                    <button className="btn btn--primary btn--lg" onClick={() => onNavigate('register')}>Apply for Agency Account</button>
                    <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('login')}>Agent Login</button>
                  </>
                )}
              </div>
            </div>

            {/* Right — dashboard mockup */}
            <div style={{ animation: 'float-agency 7s ease-in-out infinite' }}>
              <div style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: 24 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 16 }}>
                  {['#ff5f57','#febc2e','#28c840'].map(c => <div key={c} style={{ width: 10, height: 10, borderRadius: '50%', background: c }} />)}
                  <span style={{ fontSize: 11, color: 'var(--slate)', marginLeft: 8 }}>Agency Dashboard — Safara Travels</span>
                </div>
                {/* Stat rows */}
                {[['Policies Issued (This Month)','1,247','#86efac'],['Monthly Revenue','KES 384,000','var(--gold)'],['Active Sub-agents','12','#6ee7b7'],['Pending Commissions','KES 48,250','#f87171']].map(([k,v,c]) => (
                  <div key={k} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '11px 14px', background: 'rgba(0,0,0,0.25)', borderRadius: 8, marginBottom: 10 }}>
                    <span style={{ fontSize: 12, color: 'var(--slate)' }}>{k}</span>
                    <span style={{ fontSize: 15, fontWeight: 700, color: c }}>{v}</span>
                  </div>
                ))}
                {/* Recent policies mini-table */}
                <div style={{ marginTop: 14, background: 'rgba(0,0,0,0.2)', borderRadius: 8, overflow: 'hidden' }}>
                  <div style={{ padding: '8px 14px', borderBottom: '1px solid rgba(255,255,255,0.06)', fontSize: 10, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--slate)' }}>Recent Policies</div>
                  {[['Amina K.','Schengen','Active'],['James M.','Worldwide','Pending'],['Patricia W.','East Africa','Active']].map(([name,dest,status]) => (
                    <div key={name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '9px 14px', borderBottom: '1px solid rgba(255,255,255,0.04)', fontSize: 12 }}>
                      <span style={{ color: 'rgba(255,255,255,0.8)' }}>{name}</span>
                      <span style={{ color: 'var(--slate)', fontSize: 11 }}>{dest}</span>
                      <span style={{ fontSize: 10, fontWeight: 700, color: status === 'Active' ? '#86efac' : 'var(--gold)', background: status === 'Active' ? 'rgba(49,99,49,0.2)' : 'rgba(246,166,35,0.1)', padding: '2px 8px', borderRadius: 20, border: `1px solid ${status === 'Active' ? 'rgba(49,99,49,0.4)' : 'rgba(246,166,35,0.3)'}` }}>{status}</span>
                    </div>
                  ))}
                </div>
                <div style={{ marginTop: 12, padding: '10px 14px', background: 'rgba(49,99,49,0.18)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 8, fontSize: 12, color: '#86efac', textAlign: 'center' }}>
                  🔔 3 policies pending approval — <span style={{ textDecoration: 'underline', cursor: 'pointer' }}>Review now</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── Stats bar ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 80px' }}>
        <div className="container">
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? 'repeat(2,1fr)' : 'repeat(4,1fr)', gap: 1, background: 'var(--glass-border)', borderRadius: 'var(--radius-lg)', overflow: 'hidden', border: '1px solid var(--glass-border)' }}>
            {[['200+','Partner Agencies'],['KES 2B+','Premiums Processed'],['15+','Insurer Partners'],['4 min','Avg. Certificate Time']].map(([n,l]) => (
              <div key={l} style={{ background: 'var(--navy)', padding: '32px 28px', textAlign: 'center' }}>
                <div style={{ fontFamily: 'var(--font-display)', fontSize: 36, fontWeight: 800, color: 'var(--gold)', lineHeight: 1 }}>{n}</div>
                <div style={{ fontSize: 13, color: 'var(--slate)', marginTop: 8 }}>{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Features grid ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">Platform Features</p>
            <h2 className="section-title">Everything Your Agency Needs</h2>
            <p style={{ color: 'var(--slate)', maxWidth: 560, margin: '0 auto' }}>One platform to replace the spreadsheets, manual PDFs, and chasing insurers for commissions.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : tablet ? 'repeat(2,1fr)' : 'repeat(3,1fr)', gap: 20, marginTop: 48 }}>
            {FEATURES.map(f => (
              <div key={f.title} className="agency-feature-card" style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '28px 24px', cursor: 'default' }}>
                <div style={{ fontSize: 28, marginBottom: 14 }}>{f.icon}</div>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 16, fontWeight: 700, marginBottom: 10 }}>{f.title}</h3>
                <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75 }}>{f.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── How it works ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">Getting Started</p>
            <h2 className="section-title">Up and Running in 48 Hours</h2>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : 'repeat(3,1fr)', gap: 32, marginTop: 48, position: 'relative' }}>
            {/* connecting line */}
            {!mobile && <div style={{ position: 'absolute', top: 36, left: '15%', right: '15%', height: 1, background: 'linear-gradient(90deg,transparent,rgba(49,99,49,0.5),transparent)', pointerEvents: 'none' }} />}
            {STEPS.map((s, i) => (
              <div key={s.n} style={{ textAlign: 'center', padding: '0 16px' }}>
                <div style={{ width: 72, height: 72, borderRadius: '50%', background: `linear-gradient(135deg,var(--indigo),var(--indigo-glow))`, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px', fontSize: 20, fontFamily: 'var(--font-display)', fontWeight: 800, color: '#fff', boxShadow: '0 0 30px rgba(49,99,49,0.4)', border: '2px solid rgba(49,99,49,0.5)' }}>{s.n}</div>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 18, fontWeight: 700, marginBottom: 10 }}>{s.title}</h3>
                <p style={{ color: 'var(--slate)', fontSize: 14, lineHeight: 1.75 }}>{s.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Pricing note ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <div style={{ background: 'linear-gradient(135deg,var(--navy-light),rgba(49,99,49,0.08))', border: '1px solid var(--glass-border-bright)', borderRadius: 'var(--radius-xl)', padding: mobile ? '36px 24px' : '56px 60px', display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: mobile ? 32 : 60, alignItems: 'center' }}>
            <div>
              <p className="section-label">Pricing</p>
              <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(26px,3vw,42px)', fontWeight: 800, lineHeight: 1.1, marginBottom: 18 }}>
                Zero Upfront Cost.<br />
                <em style={{ fontStyle: 'normal', color: 'var(--gold)' }}>You Earn, We Earn.</em>
              </h2>
              <p style={{ color: 'var(--slate)', fontSize: 15, lineHeight: 1.8 }}>
                Maljani operates on a pure commission model. There are no monthly fees, no sign-up costs, and no minimums. Your agency earns a commission on every policy sold — paid monthly into your registered account.
              </p>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              {[['✓ No monthly subscription fee',''], ['✓ Commission starts from Day 1',''], ['✓ Real-time commission tracking',''], ['✓ Monthly direct bank payouts',''], ['✓ Volume bonuses for top agencies','🏆']].map(([l, badge]) => (
                <div key={l} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 18px', background: 'rgba(255,255,255,0.04)', border: '1px solid var(--glass-border)', borderRadius: 10 }}>
                  <span style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)' }}>{l}</span>
                  {badge && <span style={{ fontSize: 16 }}>{badge}</span>}
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ── Testimonial ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 96px' }}>
        <div className="container">
          <blockquote style={{ maxWidth: 720, margin: '0 auto', background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-xl)', padding: mobile ? '32px 24px' : '48px 52px', textAlign: 'center' }}>
            <div style={{ fontSize: 52, color: 'var(--indigo-glow)', lineHeight: 1, marginBottom: 20, opacity: 0.6 }}>&ldquo;</div>
            <p style={{ color: 'rgba(255,255,255,0.88)', fontSize: 17, lineHeight: 1.85, marginBottom: 28, fontStyle: 'italic' }}>
              As a travel agency, we issue 50–80 policies a month. The Maljani agency dashboard has cut our paperwork time by 90%. Commission is tracked in real time, and the white-labelled certificates give us a professional edge our clients love.
            </p>
            <footer style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14 }}>
              <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'var(--glass-bg-md)', border: '1px solid var(--glass-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 24 }}>👨🏿‍💼</div>
              <div style={{ textAlign: 'left' }}>
                <div style={{ fontWeight: 700, fontSize: 15 }}>James M.</div>
                <div style={{ fontSize: 13, color: 'var(--slate)' }}>Managing Director, Safara Travels — Nairobi</div>
              </div>
            </footer>
          </blockquote>
        </div>
      </section>

      {/* ── CTA ── */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 120px' }}>
        <div className="container">
          <div style={{ background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow) 100%)', borderRadius: 'var(--radius-xl)', padding: mobile ? '48px 24px' : '80px 60px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
            <div style={{ position: 'absolute', top: -40, right: -40, width: 300, height: 300, borderRadius: '50%', background: 'rgba(255,255,255,0.05)', pointerEvents: 'none' }} aria-hidden="true" />
            <p style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.16em', textTransform: 'uppercase', opacity: 0.7, marginBottom: 14 }}>Ready to grow your agency?</p>
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(28px,3.5vw,48px)', fontWeight: 800, marginBottom: 18, lineHeight: 1.1 }}>Join 200+ Agencies on Maljani.</h2>
            <p style={{ opacity: 0.85, fontSize: 16, marginBottom: 36, maxWidth: 460, margin: '0 auto 36px' }}>Apply today. Approval in 1–2 business days. Start issuing same week.</p>
            <div style={{ display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' }}>
              {isLoggedIn && isAgent ? (
                <button className="btn btn--gold btn--lg" onClick={() => onNavigate('dashboard')}>Open Agency Dashboard &rarr;</button>
              ) : (
                <>
                  <button className="btn btn--gold btn--lg" onClick={() => onNavigate('register')}>Apply for Agency Account &rarr;</button>
                  <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('login')}>Agent Login</button>
                </>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default AgenciesPage;
