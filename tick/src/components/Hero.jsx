import React, { useState } from "react";
import { useQuery } from "urql";
import { useAuth } from "../lib/AuthContext";

const GET_REGIONS = `
  query GetRegions {
    regions(first: 50) {
      nodes { id databaseId name slug }
    }
  }
`;

const Hero = ({ onStart, onNavigate }) => {
  const { user, role } = useAuth();
  const isAgent = role === "agent" || role === "administrator";
  const [step, setStep] = useState(1);
  const [activeTab, setActiveTab] = useState(0);
  const today = new Date().toISOString().split("T")[0];
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().split("T")[0];
  const [form, setForm] = useState({
    dest: "",
    departure: today,
    returnDate: nextWeek,
    travelers: "1 Person",
    coverage: "Standard",
  });
  const [{ data }] = useQuery({ query: GET_REGIONS });
  const regions = data?.regions?.nodes || [];
  const tabs = ["Individual", "Group", "Agency"];

  const handleNext = () => {
    if (step === 1 && !form.dest) return;
    if (step < 3) setStep(step + 1);
    else handleSubmit();
  };
  const handleBack = () => { if (step > 1) setStep(step - 1); };
  const handleSubmit = () => {
    if (!form.dest) return;
    onStart({ region: form.dest, departure: form.departure, returnDate: form.returnDate, passengers: parseInt(form.travelers) || 1 });
  };

  const StepBar = ({ currentStep }) => (
    <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 28, justifyContent: "center" }}>
      {[1, 2, 3].map((s) => (
        <React.Fragment key={s}>
          <div style={{
            width: 24, height: 24, borderRadius: "50%", display: "flex",
            alignItems: "center", justifyContent: "center", fontSize: 11, fontWeight: 800,
            background: s < currentStep ? "var(--gold)" : s === currentStep ? "var(--indigo)" : "var(--glass-bg)",
            border: `2px solid ${s <= currentStep ? (s < currentStep ? "var(--gold)" : "var(--indigo)") : "var(--glass-border)"}`,
            color: s <= currentStep ? "#fff" : "var(--slate)", transition: "all 0.3s ease",
          }}>{s < currentStep ? "✓" : s}</div>
          {s < 3 && <div style={{ width: 30, height: 2, background: s < currentStep ? "var(--gold)" : "var(--glass-border)", transition: "all 0.3s ease" }} />}
        </React.Fragment>
      ))}
    </div>
  );

  const field = (label, id, children) => (
    <div style={{ marginBottom: 18 }}>
      <label htmlFor={id} style={{ display: "block", fontSize: 11, fontWeight: 700, letterSpacing: "0.08em", textTransform: "uppercase", color: "var(--slate)", marginBottom: 8 }}>{label}</label>
      {children}
    </div>
  );

  return (
    <section id="hero-top" style={{ position: "relative", zIndex: 1, minHeight: "100vh", display: "flex", alignItems: "center", padding: "128px 0 80px" }}>
      <div style={{
        position: "absolute", right: "-8%", top: "50%", transform: "translateY(-50%)",
        width: 560, height: 560, border: "1px dashed rgba(49,99,49,0.18)", borderRadius: "50%",
        pointerEvents: "none", animation: "spin-slow 80s linear infinite",
      }} aria-hidden="true" />
      <style>{`@keyframes spin-slow { to { transform: translateY(-50%) rotate(360deg); } } @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }`}</style>

      <div className="container">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 460px", gap: 72, alignItems: "center" }}>

          {/* ── Left column ── */}
          {isAgent ? (
            /* Agent copy */
            <div>
              <p className="section-label">For Insurance Agencies</p>
              <h1 className="reveal" style={{ fontFamily: "var(--font-display)", fontSize: "clamp(36px,4vw,58px)", fontWeight: 800, lineHeight: 1.05, letterSpacing: "-0.025em", marginBottom: 18 }}>
                Issue Policies in Bulk.<br />
                <em style={{ fontStyle: "normal", background: "linear-gradient(135deg,var(--gold),var(--gold-light))", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent", backgroundClip: "text" }}>From One Dashboard.</em>
              </h1>
              {user?.name && (
                <div style={{ fontSize: 13, color: "#86efac", marginBottom: 14, fontWeight: 600 }}>Welcome back, {user.name}</div>
              )}
              <p className="reveal reveal-delay-1" style={{ color: "var(--slate)", fontSize: 16, lineHeight: 1.8, marginBottom: 28, maxWidth: 520 }}>
                Join 200+ agencies already issuing travel insurance certificates through Maljani.
                Branded certificates, commission tracking, and team management &mdash; all included.
              </p>
              <div className="reveal reveal-delay-2" style={{ display: "flex", flexDirection: "column", gap: 10, marginBottom: 32 }}>
                {["\u2713 White-labelled PDF certificates", "\u2713 Commission dashboard (real-time)", "\u2713 Sub-agent accounts & role controls", "\u2713 API access for system integrations"].map(f => (
                  <div key={f} style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 15 }}>
                    <span style={{ color: "#22c55e", fontSize: 16 }}>{f[0]}</span>
                    <span style={{ color: "rgba(255,255,255,0.8)" }}>{f.slice(1)}</span>
                  </div>
                ))}
              </div>
              <div className="reveal reveal-delay-3" style={{ display: "flex", gap: 14, flexWrap: "wrap" }}>
                {user ? (
                  <>
                    <button className="btn btn--primary btn--lg" onClick={() => onNavigate?.("dashboard")}>Open Dashboard &rarr;</button>
                    <button className="btn btn--ghost btn--lg" onClick={() => onStart?.({ region: "", departure: today, returnDate: nextWeek, passengers: 1 })}>New Quote</button>
                  </>
                ) : (
                  <>
                    <button className="btn btn--primary btn--lg" onClick={() => onNavigate?.("register")}>Apply for Agency Account</button>
                    <button className="btn btn--ghost btn--lg" onClick={() => onNavigate?.("login")}>Agent Login</button>
                  </>
                )}
              </div>
            </div>
          ) : (
            /* Consumer copy */
            <div>
              <div className="reveal" style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 22 }}>
                <div style={{ width: 28, height: 2, background: "var(--gold)", flexShrink: 0 }} />
                <span style={{ fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", textTransform: "uppercase", color: "var(--gold)" }}>East Africa&apos;s #1 Travel Insurance Hub</span>
              </div>
              <h1 className="reveal reveal-delay-1" style={{ fontFamily: "var(--font-display)", fontSize: "clamp(40px,5vw,72px)", fontWeight: 800, lineHeight: 1.0, letterSpacing: "-0.025em", marginBottom: 22 }}>
                Travel Insurance{" "}
                <em style={{ fontStyle: "normal", background: "linear-gradient(135deg,var(--indigo-glow),#86efac)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent", backgroundClip: "text" }}>Center.</em>
              </h1>
              <p className="reveal reveal-delay-2" style={{ fontSize: 18, color: "var(--slate)", lineHeight: 1.75, marginBottom: 36, maxWidth: 520 }}>
                Compare policies from Africa&apos;s leading insurers in seconds.
                Instant certificates. Zero paperwork. Built for travelers and agencies.
              </p>
              <div className="reveal reveal-delay-3" style={{ display: "flex", alignItems: "center", gap: 14, flexWrap: "wrap", marginBottom: 18 }}>
                <button className="btn btn--primary btn--lg" onClick={() => document.getElementById("policy-showcase")?.scrollIntoView({ behavior: "smooth" })}>Compare Policies &rarr;</button>
                <button className="btn btn--ghost btn--lg" onClick={() => document.getElementById("agencies")?.scrollIntoView({ behavior: "smooth" })}>For Agencies</button>
              </div>
              <div className="reveal reveal-delay-4" style={{ display: "flex", alignItems: "center", gap: 16, flexWrap: "wrap", fontSize: 13, color: "var(--slate-dark)" }}>
                {["\u2713 No sign-up needed to compare", "\u2713 Embassy-verified certificates", "\u2713 15+ Partner insurers"].map(t => (
                  <span key={t} style={{ display: "flex", alignItems: "center", gap: 5 }}>
                    <em style={{ color: "#22c55e", fontStyle: "normal" }}>{t[0]}</em>{t.slice(1)}
                  </span>
                ))}
                <button
                  onClick={() => onNavigate?.('verify')}
                  style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 13, color: 'var(--gold)', fontWeight: 600, padding: 0, textDecoration: 'underline', textUnderlineOffset: 3 }}
                >
                  Verify a certificate →
                </button>
              </div>
            </div>
          )}

          {/* ── Right column ── */}
          {isAgent ? (
            /* Agency dashboard mockup */
            <div className="reveal reveal-delay-2" style={{ background: "var(--glass-bg)", border: "1px solid var(--glass-border)", borderRadius: "var(--radius-lg)", padding: 24, fontFamily: "var(--font-body)", animation: "float 7s ease-in-out infinite" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 16 }}>
                {["#ff5f57","#febc2e","#28c840"].map(c => <div key={c} style={{ width: 10, height: 10, borderRadius: "50%", background: c }} />)}
                <span style={{ fontSize: 11, color: "var(--slate)", marginLeft: 8 }}>Agency Dashboard</span>
              </div>
              {[["Policies Issued","1,247","#86efac"],["Monthly Revenue","KES 384K","var(--gold)"],["Active Sub-agents","12","#6ee7b7"],["Pending Commissions","KES 48K","#f87171"]].map(([k,v,c]) => (
                <div key={k} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 14px", background: "rgba(0,0,0,0.25)", borderRadius: 8, marginBottom: 10 }}>
                  <span style={{ fontSize: 12, color: "var(--slate)" }}>{k}</span>
                  <span style={{ fontSize: 15, fontWeight: 700, color: c }}>{v}</span>
                </div>
              ))}
              <div style={{ marginTop: 14, padding: "10px 14px", background: "rgba(49,99,49,0.18)", border: "1px solid rgba(49,99,49,0.3)", borderRadius: 8, fontSize: 12, color: "#86efac", textAlign: "center" }}>
                🔔 3 policies pending approval
              </div>
            </div>
          ) : (
            /* Consumer quote wizard card */
            <div className="reveal reveal-delay-2">
              <div style={{
                background: "var(--glass-bg-md)", border: "1px solid var(--glass-border-bright)",
                borderRadius: "var(--radius-xl)", padding: 32, backdropFilter: "blur(24px)",
                boxShadow: "var(--shadow-card), inset 0 1px 0 rgba(255,255,255,0.08)",
                position: "relative", overflow: "hidden", animation: "float 7s ease-in-out infinite",
              }} role="form" aria-label="Get an insurance quote">
                <div style={{ position: "absolute", top: -1, left: 20, right: 20, height: 2, background: "linear-gradient(90deg,transparent,var(--indigo-glow),transparent)", borderRadius: 2 }} />
                <h2 style={{ fontFamily: "var(--font-display)", fontSize: 20, fontWeight: 700, marginBottom: 6 }}>Get Your Quote</h2>
                <p style={{ fontSize: 13, color: "var(--slate)", marginBottom: 24 }}>Comparison takes less than 60 seconds</p>
                <StepBar currentStep={step} />

                {step === 1 && (
                  <div className="fade-in">
                    <div role="tablist" style={{ display: "flex", background: "rgba(0,0,0,0.3)", borderRadius: "var(--radius-md)", padding: 4, marginBottom: 22, gap: 4 }}>
                      {tabs.map((t, i) => (
                        <button key={t} role="tab" aria-selected={activeTab === i} onClick={() => setActiveTab(i)}
                          style={{ flex: 1, padding: "10px 12px", borderRadius: 10, border: "none", cursor: "pointer", fontSize: 13, fontWeight: 600, fontFamily: "var(--font-body)", transition: "all 0.2s",
                            background: activeTab === i ? "var(--indigo)" : "transparent",
                            color: activeTab === i ? "#fff" : "var(--slate)",
                          }}>{t}</button>
                      ))}
                    </div>
                    {field("Where are you going?", "dest", (
                      <div style={{ display: "flex", flexWrap: "wrap", gap: 8, marginBottom: 16 }}>
                        {(regions.length > 0 ? regions : [
                          { name: "East Africa", slug: "east-africa" },
                          { name: "Schengen", slug: "schengen" },
                          { name: "Worldwide", slug: "worldwide" },
                          { name: "Asia", slug: "asia" },
                        ]).map(r => (
                          <button key={r.slug || r.name} onClick={() => setForm(f => ({ ...f, dest: r.slug || r.name }))}
                            style={{ padding: "10px 16px", borderRadius: 12, fontSize: 13, fontWeight: 600, cursor: "pointer",
                              border: `1px solid ${form.dest === (r.slug || r.name) ? "var(--gold)" : "var(--glass-border)"}`,
                              background: form.dest === (r.slug || r.name) ? "rgba(246,166,35,0.15)" : "var(--glass-bg)",
                              color: form.dest === (r.slug || r.name) ? "var(--gold)" : "var(--white)", transition: "all 0.2s",
                            }}>{r.name}</button>
                        ))}
                      </div>
                    ))}
                    {field("Or Select Other", "dest-select",
                      <select id="dest-select" className="form-input" value={form.dest} onChange={e => setForm(f => ({ ...f, dest: e.target.value }))}>
                        <option value="">Select destination region&hellip;</option>
                        {regions.map(r => <option key={r.id} value={r.slug}>{r.name}</option>)}
                      </select>
                    )}
                    <button className="btn btn--primary" style={{ width: "100%", justifyContent: "center", marginTop: 12 }} onClick={handleNext} disabled={!form.dest}>
                      Next: Trip Dates &rarr;
                    </button>
                  </div>
                )}

                {step === 2 && (
                  <div className="fade-in">
                    <div style={{ marginBottom: 20, padding: 12, background: "rgba(255,255,255,0.05)", borderRadius: 12, border: "1px solid var(--glass-border)", fontSize: 13 }}>
                      Destination: <strong style={{ color: "var(--gold)" }}>{form.dest}</strong>
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                      {field("Departure", "dep", <input id="dep" type="date" className="form-input" value={form.departure} min={today} onChange={e => setForm(f => ({ ...f, departure: e.target.value }))} />)}
                      {field("Return", "ret", <input id="ret" type="date" className="form-input" value={form.returnDate} min={form.departure} onChange={e => setForm(f => ({ ...f, returnDate: e.target.value }))} />)}
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1.5fr", gap: 12, marginTop: 12 }}>
                      <button className="btn btn--ghost" onClick={handleBack}>Back</button>
                      <button className="btn btn--primary" style={{ justifyContent: "center" }} onClick={handleNext}>Next: Travelers &rarr;</button>
                    </div>
                  </div>
                )}

                {step === 3 && (
                  <div className="fade-in">
                    <div style={{ marginBottom: 20, padding: 12, background: "rgba(255,255,255,0.05)", borderRadius: 12, border: "1px solid var(--glass-border)", fontSize: 13 }}>
                      Trip: <strong style={{ color: "var(--gold)" }}>{form.dest}</strong> &middot; {form.departure} to {form.returnDate}
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                      {field("Travelers", "travelers",
                        <select id="travelers" className="form-input" value={form.travelers} onChange={e => setForm(f => ({ ...f, travelers: e.target.value }))}>
                          {["1 Person","2 People","3–5 People","6+ People"].map(o => <option key={o}>{o}</option>)}
                        </select>
                      )}
                      {field("Coverage", "coverage",
                        <select id="coverage" className="form-input" value={form.coverage} onChange={e => setForm(f => ({ ...f, coverage: e.target.value }))}>
                          {["Standard","Comprehensive","Premium"].map(o => <option key={o}>{o}</option>)}
                        </select>
                      )}
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1.5fr", gap: 12, marginTop: 12 }}>
                      <button className="btn btn--ghost" onClick={handleBack}>Back</button>
                      <button className="btn btn--primary" style={{ justifyContent: "center" }} onClick={handleSubmit}>Compare Policies &rarr;</button>
                    </div>
                  </div>
                )}

                <p style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 6, marginTop: 18, fontSize: 12, color: "var(--slate-dark)" }}>
                  🔒 100% secure &middot; Best Price Guarantee
                </p>
              </div>
            </div>
          )}

        </div>
      </div>
    </section>
  );
};

export default Hero;