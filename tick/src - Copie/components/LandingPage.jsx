import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../lib/AuthContext';
import Hero from './Hero';
import CategoryFilters from './CategoryFilters';
import TrustSection from './TrustSection';
import ProcessSection from './ProcessSection';
import PolicyShowcase from './PolicyShowcase';
import FAQSection from './FAQSection';
import TrustedPartners from './TrustedPartners';

const parseTags = (str) => {
  if (!str) return [];
  return str.split(',').map(t => t.trim()).filter(Boolean);
};

const fmtPrice = (price, currency = 'KES') => {
  if (price === null || price === undefined) return null;
  return `${currency} ${Number(price).toLocaleString('en-KE')}`;
};

const getDisplayFeatures = (p) => {
  if (p.policyFeatureTags) {
    const t = p.policyFeatureTags.split(',').map(s => s.trim()).filter(Boolean);
    if (t.length > 0) return t;
  }
  if (p.policyCoverDetails) {
    const matches = p.policyCoverDetails.match(/<b>(.*?)<\/b>/g) || [];
    return matches.map(m => m.replace(/<\/?b>|:/g, '').trim()).slice(0, 3);
  }
  return [];
};

/* Strip HTML and return individual benefit strings */
const parseBenefits = (html) => {
  if (!html) return [];
  return html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/li>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ')
    .split(/\n|;/)
    .map(s => s.trim().replace(/^[-\u2013\u2022\u00b7*\u2713\u2717]+\s*/, '').trim())
    .filter(s => s.length > 5 && s.length < 220);
};

const normalizeBenefit = (s) =>
  s.toLowerCase().replace(/[^a-z0-9\s]/g, '').replace(/\s+/g, ' ').trim();

const keyWords = (s) =>
  normalizeBenefit(s).split(' ').filter(w => w.length > 3);

/* Returns subset of refList items that appear in ALL other lists */
const findCommonBenefits = (allLists) => {
  if (allLists.length < 2) return [];
  const [first, ...rest] = allLists;
  return first.filter(benefit => {
    const kw = keyWords(benefit);
    if (kw.length === 0) return false;
    return rest.every(list =>
      list.some(item => {
        const ik = keyWords(item);
        const matches = kw.filter(w => ik.includes(w) || normalizeBenefit(item).includes(w));
        return matches.length >= Math.ceil(kw.length * 0.5);
      })
    );
  });
};

const policyHasBenefit = (policy, benefitStr) => {
  const kw = keyWords(benefitStr);
  if (kw.length === 0) return false;
  const haystack = normalizeBenefit(
    (policy.policyBenefits || '') + ' ' + (policy.policyCoverDetails || '')
  );
  const matches = kw.filter(w => haystack.includes(w));
  return matches.length >= Math.ceil(kw.length * 0.5);
};

/** Days between two YYYY-MM-DD strings (inclusive) */
const tripDays = (dep, ret) => {
  if (!dep || !ret) return 0;
  const ms = new Date(ret) - new Date(dep);
  return Math.max(1, Math.round(ms / 86400000) + 1);
};

/** Find premium for trip length from day-bracket array */
const bracketPremium = (brackets, days) => {
  if (!brackets || !brackets.length) return null;
  const match = brackets.find(b => days >= b.from && days <= b.to);
  return match ? match.premium : null;
};

const fieldStyle = { display: 'flex', flexDirection: 'column', gap: 6 };
const labelStyle = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--gold)' };
const inputStyle = { padding: '10px 14px', borderRadius: 8, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: '#fff', fontSize: 14, fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box' };

/* Compare Bar */
const CompareBar = ({ selected, onRemove, onClear, onCompare }) => {
  if (selected.length < 2) return null;
  return (
    <div role="region" aria-label="Policy comparison bar" style={{ position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 900, background: 'rgba(8,14,39,0.96)', backdropFilter: 'blur(18px)', borderTop: '1px solid rgba(49,99,49,0.35)', padding: '16px 40px', display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginRight: 4 }}>
        <div style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--indigo-glow)', boxShadow: '0 0 8px var(--indigo-glow)' }} aria-hidden="true" />
        <span style={{ fontWeight: 700, fontSize: 14 }}>Comparing {selected.length}/3</span>
      </div>
      {selected.map(p => (
        <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 8, background: 'var(--glass-bg-md)', border: '1px solid var(--glass-border)', borderRadius: 10, padding: '8px 14px', fontSize: 13, fontWeight: 600 }}>
          {p.policyInsurerLogo && (
            <img src={p.policyInsurerLogo} alt="" style={{ width: 20, height: 20, objectFit: 'contain', borderRadius: 4, flexShrink: 0 }} />
          )}
          <span style={{ maxWidth: 140, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.title}</span>
          <button onClick={() => onRemove(p.id)} aria-label={`Remove ${p.title} from comparison`} style={{ background: 'none', border: 'none', color: 'var(--slate)', fontSize: 16, cursor: 'pointer', lineHeight: 1, padding: '0 2px' }}>x</button>
        </div>
      ))}
      <div style={{ marginLeft: 'auto', display: 'flex', gap: 10 }}>
        <button className="btn btn--ghost btn--sm" onClick={onClear}>Clear</button>
        <button className="btn btn--primary btn--sm" onClick={onCompare}>Compare Now</button>
      </div>
    </div>
  );
};

/* Section divider row */
const SectionRow = ({ label, cols, accent }) => (
  <tr>
    <td colSpan={cols + 1} style={{
      padding: '10px 16px 8px',
      background: accent ? 'rgba(49,99,49,0.10)' : 'rgba(255,255,255,0.03)',
      borderTop: '1px solid rgba(255,255,255,0.08)',
      borderBottom: '1px solid rgba(255,255,255,0.08)',
    }}>
      <span style={{
        fontSize: 10, fontWeight: 800, letterSpacing: '0.12em', textTransform: 'uppercase',
        color: accent ? '#86efac' : 'var(--slate)',
      }}>{label}</span>
    </td>
  </tr>
);

/* Date picker shown before opening Compare Modal */
const CompareDatePicker = ({ onConfirm, onSkip, onClose }) => {
  const today = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0];
  const [dep, setDep] = useState(today);
  const [ret, setRet] = useState(nextWeek);
  const [pax, setPax] = useState(1);
  const days = tripDays(dep, ret);
  return (
    <div
      style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.88)', backdropFilter: 'blur(16px)', zIndex: 1010, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}
      onClick={onClose}
    >
      <div
        style={{ background: 'var(--navy-mid)', border: '1px solid var(--glass-border-bright)', borderRadius: 'var(--radius-xl)', padding: '2rem', width: '100%', maxWidth: 400, position: 'relative' }}
        onClick={e => e.stopPropagation()}
      >
        <button onClick={onClose} style={{ position: 'absolute', top: 14, right: 16, background: 'none', border: 'none', color: 'var(--slate)', fontSize: 22, cursor: 'pointer', lineHeight: 1 }}>x</button>
        <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 800, marginBottom: 6, marginTop: 0 }}>
          Your <span style={{ color: 'var(--gold)' }}>Trip</span>
        </h3>
        <p style={{ fontSize: 12, color: 'var(--slate)', marginBottom: 22, marginTop: 0 }}>Enter dates to see exact pricing for your trip duration.</p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <div style={fieldStyle}>
              <label style={labelStyle}>Departure</label>
              <input type="date" style={inputStyle} min={today} value={dep} onChange={e => setDep(e.target.value)} />
            </div>
            <div style={fieldStyle}>
              <label style={labelStyle}>Return</label>
              <input type="date" style={inputStyle} min={dep} value={ret} onChange={e => setRet(e.target.value)} />
            </div>
          </div>
          <div style={fieldStyle}>
            <label style={labelStyle}>Travellers</label>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <button style={{ ...inputStyle, width: 40, textAlign: 'center', cursor: 'pointer', flexShrink: 0, padding: '10px 0' }}
                onClick={() => setPax(p => Math.max(1, p - 1))}>-</button>
              <span style={{ fontSize: 18, fontWeight: 800, minWidth: 24, textAlign: 'center', flex: 1 }}>{pax}</span>
              <button style={{ ...inputStyle, width: 40, textAlign: 'center', cursor: 'pointer', flexShrink: 0, padding: '10px 0' }}
                onClick={() => setPax(p => p + 1)}>+</button>
            </div>
          </div>
        </div>

        {days > 0 && (
          <div style={{ margin: '16px 0 0', padding: '10px 14px', background: 'rgba(49,99,49,0.12)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 8, fontSize: 13, color: 'rgba(255,255,255,0.8)' }}>
            Trip: <strong style={{ color: 'var(--gold)' }}>{days} day{days !== 1 ? 's' : ''}</strong> &nbsp;&middot;&nbsp; {pax} traveller{pax !== 1 ? 's' : ''}
          </div>
        )}

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 20 }}>
          <button
            className="btn btn--primary"
            style={{ width: '100%', justifyContent: 'center' }}
            disabled={!dep || !ret}
            onClick={() => onConfirm({ departure: dep, returnDate: ret, passengers: pax })}
          >
            Compare Plans &rarr;
          </button>
          <button
            className="btn btn--ghost btn--sm"
            style={{ width: '100%', justifyContent: 'center' }}
            onClick={onSkip}
          >
            Skip &mdash; compare without dates
          </button>
        </div>
      </div>
    </div>
  );
};

/* Compare Modal */
const CompareModal = ({ selected, onClose, onPickPolicy, onKeepComparing, compareDates }) => {
  if (!selected.length) return null;

  const allBenefitLists = selected.map(p => parseBenefits(p.policyBenefits));
  const commonBenefits  = findCommonBenefits(allBenefitLists);

  const uniqueBenefits = allBenefitLists.map(list =>
    list.filter(b => !commonBenefits.some(c => {
      const ck = keyWords(c), bk = keyWords(b);
      return ck.filter(w => bk.includes(w)).length >= Math.ceil(ck.length * 0.5);
    })).slice(0, 8)
  );

  const priceRange = (p) => {
    const prem = p.policyDayPremiums;
    if (!prem || prem.length === 0) return 'N/A';
    const cur = p.policyCurrency || 'KES';
    // Exact price for known trip duration
    if (compareDates?.departure && compareDates?.returnDate) {
      const d = tripDays(compareDates.departure, compareDates.returnDate);
      const perPerson = bracketPremium(prem, d);
      if (perPerson !== null) {
        const total = perPerson * (compareDates.passengers || 1);
        return (
          <div>
            <div style={{ fontWeight: 700, fontSize: 15, color: '#86efac' }}>
              {cur} {total.toLocaleString('en-KE')} total
            </div>
            <div style={{ fontSize: 11, color: 'var(--slate)', marginTop: 2 }}>
              {cur} {perPerson.toLocaleString('en-KE')}/person &middot; {d} days
            </div>
          </div>
        );
      }
    }
    // Fallback: price range
    const prices = prem.map(b => Number(b.premium)).filter(n => !isNaN(n));
    if (prices.length === 0) return 'N/A';
    const min = Math.min(...prices), max = Math.max(...prices);
    if (min === max) return fmtPrice(min, cur);
    return `${cur} ${min.toLocaleString('en-KE')} \u2013 ${max.toLocaleString('en-KE')}`;
  };

  const colW = `${Math.floor(85 / selected.length)}%`;
  const cellBase = { padding: '11px 16px', verticalAlign: 'top', fontSize: 13, borderBottom: '1px solid rgba(255,255,255,0.05)' };
  const labelCell = { ...cellBase, width: '15%', color: 'var(--slate)', fontWeight: 600, whiteSpace: 'nowrap', fontSize: 12 };

  return (
    <div
      style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.92)', backdropFilter: 'blur(16px)', zIndex: 1000, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', padding: '2rem', overflowY: 'auto' }}
      onClick={onClose}
    >
      <div
        style={{ background: 'var(--navy-mid)', border: '1px solid var(--glass-border-bright)', borderRadius: 'var(--radius-xl)', width: '100%', maxWidth: 920, position: 'relative', marginTop: 20, marginBottom: 40 }}
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div style={{ padding: '24px 28px 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div>
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.5rem', marginBottom: 4 }}>Policy Comparison</h2>
            <p style={{ fontSize: 12, color: 'var(--slate)' }}>
              {compareDates?.departure
                ? `${compareDates.departure} → ${compareDates.returnDate} · ${tripDays(compareDates.departure, compareDates.returnDate)} days · ${compareDates.passengers || 1} traveller${(compareDates.passengers || 1) !== 1 ? 's' : ''}`
                : `Comparing ${selected.length} ${selected.length === 1 ? 'policy' : 'policies'} side by side`
              }
            </p>
          </div>
          <button onClick={onClose} aria-label="Close comparison" style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', color: 'var(--slate)', fontSize: 18, borderRadius: 8, padding: '4px 12px', cursor: 'pointer', flexShrink: 0 }}>x</button>
        </div>

        <div style={{ overflowX: 'auto', padding: '20px 0 28px' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
            <thead>
              <tr style={{ borderBottom: '2px solid rgba(49,99,49,0.4)' }}>
                <th style={{ width: '15%', padding: '14px 16px' }} />
                {selected.map(p => (
                  <th key={p.id} style={{ width: colW, padding: '14px 16px', textAlign: 'left', verticalAlign: 'bottom' }}>
                    {p.policyInsurerLogo && (
                      <img src={p.policyInsurerLogo} alt={p.policyInsurerName || ''} style={{ height: 28, maxWidth: 80, objectFit: 'contain', display: 'block', marginBottom: 8, filter: 'brightness(1.1)' }} />
                    )}
                    <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 13, lineHeight: 1.3, marginBottom: 4 }}>{p.title}</div>
                    {p.policyInsurerName && <div style={{ fontSize: 11, color: 'var(--slate)' }}>{p.policyInsurerName}</div>}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              <SectionRow label="At a Glance" cols={selected.length} />

              {/* Price range */}
              <tr>
                <td style={labelCell}>Price Range</td>
                {selected.map(p => (
                  <td key={p.id} style={{ ...cellBase, width: colW }}>
                    <span style={{ fontWeight: 700, fontSize: 14, color: '#86efac' }}>{priceRange(p)}</span>
                  </td>
                ))}
              </tr>

              {/* Destinations */}
              <tr>
                <td style={labelCell}>Destinations</td>
                {selected.map(p => {
                  const regions = p.regions?.nodes?.map(r => r.name).filter(Boolean) || [];
                  return (
                    <td key={p.id} style={{ ...cellBase, width: colW }}>
                      {regions.length > 0
                        ? <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                            {regions.map(r => <span key={r} style={{ background: 'rgba(49,99,49,0.18)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 6, padding: '2px 8px', fontSize: 11, fontWeight: 600 }}>{r}</span>)}
                          </div>
                        : <span style={{ color: 'var(--slate)' }}>\u2014</span>
                      }
                    </td>
                  );
                })}
              </tr>

              {/* Coverage features */}
              <tr>
                <td style={labelCell}>Coverage</td>
                {selected.map(p => {
                  const tags = getDisplayFeatures(p);
                  return (
                    <td key={p.id} style={{ ...cellBase, width: colW }}>
                      {tags.length > 0
                        ? <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                            {tags.map(t => <span key={t} style={{ background: 'rgba(34,197,94,0.12)', border: '1px solid rgba(34,197,94,0.25)', borderRadius: 6, padding: '2px 8px', fontSize: 11, fontWeight: 600, color: '#86efac' }}>{t}</span>)}
                          </div>
                        : <span style={{ color: 'var(--slate)' }}>\u2014</span>
                      }
                    </td>
                  );
                })}
              </tr>

              {/* Unique per-policy benefits */}
              {uniqueBenefits.some(l => l.length > 0) && (() => {
                const maxRows = Math.max(...uniqueBenefits.map(l => l.length), 1);
                return (
                  <>
                    <SectionRow label="Unique Coverage \u2014 benefits specific to each policy" cols={selected.length} />
                    {Array.from({ length: maxRows }, (_, i) => (
                      <tr key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}>
                        <td style={labelCell} />
                        {selected.map((p, pi) => {
                          const item = uniqueBenefits[pi]?.[i];
                          return (
                            <td key={p.id} style={{ ...cellBase, width: colW }}>
                              {item && (
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 7 }}>
                                  <span style={{ color: '#60a5fa', fontSize: 14, lineHeight: 1.4, flexShrink: 0 }}>{'\u25c6'}</span>
                                  <span style={{ color: 'rgba(255,255,255,0.75)', lineHeight: 1.5, fontSize: 12 }}>{item}</span>
                                </div>
                              )}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </>
                );
              })()}

              {/* Coverage presence matrix */}
              {commonBenefits.length > 0 && selected.length > 1 && (() => {
                const checkItems = commonBenefits.slice(0, 10);
                return (
                  <>
                    <SectionRow label="Coverage Presence Check \u2014 verify each benefit per policy" cols={selected.length} />
                    {checkItems.map((benefit, i) => (
                      <tr key={i} style={{ background: i % 2 === 0 ? 'rgba(255,255,255,0.02)' : 'transparent', borderBottom: '1px solid rgba(255,255,255,0.03)' }}>
                        <td style={{ ...labelCell, fontSize: 11, whiteSpace: 'normal', lineHeight: 1.4 }}>
                          {benefit.length > 40 ? benefit.slice(0, 37) + '\u2026' : benefit}
                        </td>
                        {selected.map(p => {
                          const has = policyHasBenefit(p, benefit);
                          return (
                            <td key={p.id} style={{ ...cellBase, width: colW, textAlign: 'center' }}>
                              {has
                                ? <span style={{ color: '#22c55e', fontSize: 16 }} title="Covered">{'\u2713'}</span>
                                : <span style={{ color: 'rgba(255,255,255,0.18)', fontSize: 16 }} title="Not mentioned">{'\u2717'}</span>
                              }
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </>
                );
              })()}

              {/* Per-policy action row */}
              <tr style={{ borderTop: '2px solid rgba(49,99,49,0.3)', background: 'rgba(8,14,39,0.5)' }}>
                <td style={{ ...labelCell, verticalAlign: 'middle', paddingTop: 20, paddingBottom: 20, fontSize: 11, color: 'var(--slate)' }}>Actions</td>
                {selected.map(p => (
                  <td key={p.id} style={{ ...cellBase, width: colW, paddingTop: 20, paddingBottom: 20, verticalAlign: 'middle' }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                      <button
                        className="btn btn--primary btn--sm"
                        onClick={() => onPickPolicy(p)}
                        style={{ width: '100%', justifyContent: 'center', fontWeight: 700 }}
                      >
                        Get a Quote →
                      </button>
                      <button
                        className="btn btn--ghost btn--sm"
                        onClick={() => onKeepComparing(p)}
                        style={{ width: '100%', justifyContent: 'center', fontSize: 11 }}
                      >
                        Keep Comparing with this
                      </button>
                    </div>
                  </td>
                ))}
              </tr>
            </tbody>
          </table>
        </div>
        <div style={{ padding: '0 28px 20px' }}>
          <p style={{ fontSize: 11, color: 'rgba(255,255,255,0.3)', lineHeight: 1.6 }}>
            <strong style={{ color: 'rgba(255,255,255,0.45)' }}>Get a Quote</strong> proceeds directly to the purchase wizard for that policy.
            {' '}<strong style={{ color: 'rgba(255,255,255,0.45)' }}>Keep Comparing</strong> closes this view and pre-selects that policy so you can pick 1–2 others to compare.
          </p>
        </div>
      </div>
    </div>
  );
};
const LandingPage = ({ onStartWizard, onNavigate }) => {
  const { user, role } = useAuth();
  const isAgent = role === 'agent' || role === 'administrator';
  const [searchParams, setSearchParams] = useState(null);
  const [compareSelected, setCompareSelected] = useState([]);
  const [compareOpen, setCompareOpen] = useState(false);
  const [compareDates, setCompareDates] = useState(null);
  const [showDatePicker, setShowDatePicker] = useState(false);

  const onAddCompare    = (p)   => setCompareSelected(prev => prev.length < 3 && !prev.find(x => x.id === p.id) ? [...prev, p] : prev);
  const onRemoveCompare = (id)  => setCompareSelected(prev => prev.filter(p => p.id !== id));

  /* Intersection Observer for reveal animations */
  useEffect(() => {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    return () => obs.disconnect();
  }, []);

  return (
    <div>
      {!isAgent && showDatePicker && (
        <CompareDatePicker
          onConfirm={(dates) => { setCompareDates(dates); setShowDatePicker(false); setCompareOpen(true); }}
          onSkip={() => { setCompareDates(null); setShowDatePicker(false); setCompareOpen(true); }}
          onClose={() => setShowDatePicker(false)}
        />
      )}
      {!isAgent && compareOpen && (
        <CompareModal
          selected={compareSelected}
          onClose={() => setCompareOpen(false)}
          compareDates={compareDates}
          onPickPolicy={(p) => {
            setCompareOpen(false);
            onStartWizard(p.databaseId, compareDates, null);
          }}
          onKeepComparing={(p) => {
            setCompareSelected([p]);
            setCompareOpen(false);
            setTimeout(() => document.getElementById('policy-showcase')?.scrollIntoView({ behavior: 'smooth' }), 80);
          }}
        />
      )}
      {!isAgent && (
        <CompareBar
          selected={compareSelected}
          onRemove={onRemoveCompare}
          onClear={() => setCompareSelected([])}
          onCompare={() => setShowDatePicker(true)}
        />
      )}

      <Hero onStart={(data) => {
        console.log('LandingPage onStart:', data);
        setSearchParams(data);
        document.getElementById('policy-showcase')?.scrollIntoView({ behavior: 'smooth' });
      }} onNavigate={onNavigate} />

      {!isAgent && <TrustSection />}

      {user && !isAgent && (
        <div style={{ position: 'relative', zIndex: 1, padding: '0 0 20px' }}>
          <div className="container">
            <div style={{ background: 'linear-gradient(135deg,rgba(49,99,49,0.18),rgba(49,99,49,0.08))', border: '1px solid rgba(49,99,49,0.35)', borderRadius: 'var(--radius-lg)', padding: '28px 36px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 16 }}>
              <div>
                <p style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.10em', textTransform: 'uppercase', color: '#86efac', marginBottom: 6 }}>Welcome back, {user.name}</p>
                <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 700 }}>Ready to issue a new policy? <span style={{ color: 'var(--gold)' }}>You're logged in as {role}.</span></h3>
              </div>
              <div style={{ display: 'flex', gap: 12 }}>
                <button className="btn btn--ghost btn--sm" onClick={() => onNavigate('dashboard')}>Dashboard</button>
                <button className="btn btn--primary btn--sm" onClick={onStartWizard}>New Quote</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {!isAgent && (
        <CategoryFilters onSelect={(slug) => {
          setSearchParams({ region: slug });
          document.getElementById('policy-showcase')?.scrollIntoView({ behavior: 'smooth' });
        }} />
      )}

      {!isAgent && <ProcessSection />}

      {!isAgent && <PolicyShowcase
        onNavigate={onNavigate}
        searchParams={searchParams}
        compareSelected={compareSelected}
        onAddCompare={onAddCompare}
        onRemoveCompare={onRemoveCompare}
      />}

      {/* Agency CTA */}
      {!isAgent && (
      <section id="agencies" style={{ position: 'relative', zIndex: 1, padding: '0 0 110px' }}>
        <div className="container">
          <div style={{ background: 'linear-gradient(135deg,var(--navy-light) 0%,rgba(49,99,49,0.14) 100%)', border: '1px solid var(--glass-border-bright)', borderRadius: 'var(--radius-xl)', padding: '72px 60px', display: 'grid', gridTemplateColumns: '1fr 400px', gap: 60, alignItems: 'center' }}>
            <div>
              <p className="section-label">For Insurance Agencies</p>
              <h2 className="section-title reveal" style={{ fontSize: 'clamp(28px,3vw,46px)', marginBottom: 18 }}>
                Issue Policies in Bulk.<br />From One Dashboard.
              </h2>
              <p className="reveal reveal-delay-1" style={{ color: 'var(--slate)', fontSize: 16, lineHeight: 1.8, marginBottom: 28 }}>
                Join 200+ agencies already issuing travel insurance certificates through Maljani. Branded certificates, commission tracking, and team management — all included.
              </p>
              <div className="reveal reveal-delay-2" style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 32 }}>
                {['\u2713 White-labelled PDF certificates', '\u2713 Commission dashboard (real-time)', '\u2713 Sub-agent accounts & role controls', '\u2713 API access for system integrations'].map(f => (
                  <div key={f} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 15 }}>
                    <span style={{ color: '#22c55e', fontSize: 16 }}>{f[0]}</span>
                    <span style={{ color: 'rgba(255,255,255,0.8)' }}>{f.slice(1)}</span>
                  </div>
                ))}
              </div>
              <div className="reveal reveal-delay-3" style={{ display: 'flex', gap: 14, flexWrap: 'wrap' }}>
                <button className="btn btn--primary btn--lg" onClick={() => onNavigate('register')}>Apply for Agency Account</button>
                <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('login')}>Agent Login</button>
              </div>
            </div>
            {/* Dashboard preview mockup */}
            <div style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: 20, fontFamily: 'var(--font-body)' }} aria-hidden="true">
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 16 }}>
                {['#ff5f57','#febc2e','#28c840'].map(c => <div key={c} style={{ width: 10, height: 10, borderRadius: '50%', background: c }} />)}
                <span style={{ fontSize: 11, color: 'var(--slate)', marginLeft: 8 }}>Agency Dashboard</span>
              </div>
              {[['Policies Issued', '1,247', '#86efac'],['Monthly Revenue', 'KES 384K', 'var(--gold)'],['Active Sub-agents', '12', '#6ee7b7'],['Pending Commissions', 'KES 48K', '#f87171']].map(([k,v,c]) => (
                <div key={k} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '9px 12px', background: 'rgba(0,0,0,0.2)', borderRadius: 8, marginBottom: 8 }}>
                  <span style={{ fontSize: 12, color: 'var(--slate)' }}>{k}</span>
                  <span style={{ fontSize: 14, fontWeight: 700, color: c }}>{v}</span>
                </div>
              ))}
              <div style={{ marginTop: 14, padding: '10px 14px', background: 'rgba(49,99,49,0.18)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 8, fontSize: 12, color: '#86efac', textAlign: 'center' }}>
                🔔 3 policies pending approval
              </div>
            </div>
          </div>
        </div>
      </section>
      )}

      <TrustedPartners />

      {/* Testimonials */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 110px' }}>
        <div className="container">
          <div className="section-header">
            <p className="section-label">Customer Stories</p>
            <h2 className="section-title reveal">Trusted by 50,000+ Travelers</h2>
            <p className="reveal reveal-delay-1">Real experiences from real policyholders across Africa and beyond.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 24 }}>
            {[
              { quote: 'I applied for a Schengen visa and had my Maljani certificate within 4 minutes. The embassy accepted it without question. Absolutely seamless.', name: 'Amina K.', title: 'Nairobi, Kenya', emoji: '👩🏾' },
              { quote: 'As a travel agency, we issue 50–80 policies a month. The agency dashboard has cut our paperwork time by 90%. Commission is tracked in real time.', name: 'James M.', title: 'Managing Director, Safara Travels', emoji: '👨🏿‍💼' },
              { quote: 'Had a medical emergency in Dubai. Filed a claim through the insurer portal using my policy number. KES 420,000 was approved within 48 hours. Incredible.', name: 'Patricia W.', title: 'Kampala, Uganda', emoji: '👩🏾‍⚕️' },
            ].map(t => (
              <blockquote key={t.name} className="reveal" style={{ background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', borderRadius: 'var(--radius-lg)', padding: '28px 28px 24px', margin: 0, display: 'flex', flexDirection: 'column' }}>
                <div style={{ fontSize: 22, color: 'var(--indigo-glow)', marginBottom: 14, lineHeight: 1 }}>"</div>
                <p style={{ color: 'rgba(255,255,255,0.8)', fontSize: 14, lineHeight: 1.8, flex: 1, marginBottom: 20 }}>{t.quote}</p>
                <footer style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'var(--glass-bg-md)', border: '1px solid var(--glass-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22 }}>{t.emoji}</div>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: 14 }}>{t.name}</div>
                    <div style={{ fontSize: 12, color: 'var(--slate)' }}>{t.title}</div>
                  </div>
                </footer>
              </blockquote>
            ))}
          </div>
        </div>
      </section>

      <FAQSection />

      {/* Final CTA */}
      <section style={{ position: 'relative', zIndex: 1, padding: '0 0 120px' }}>
        <div className="container">
          <div style={{ background: 'linear-gradient(135deg,var(--indigo) 0%,var(--indigo-glow) 100%)', borderRadius: 'var(--radius-xl)', padding: '80px 60px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
            <div style={{ position: 'absolute', top: -40, right: -40, width: 300, height: 300, borderRadius: '50%', background: 'rgba(255,255,255,0.06)', pointerEvents: 'none' }} aria-hidden="true" />
            <p style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.16em', textTransform: 'uppercase', opacity: 0.7, marginBottom: 14 }}>Ready to travel?</p>
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 'clamp(28px,3.5vw,52px)', fontWeight: 800, marginBottom: 18, lineHeight: 1.1 }}>Get Covered in 3 Minutes.</h2>
            <p style={{ opacity: 0.85, fontSize: 17, marginBottom: 36, maxWidth: 480, margin: '0 auto 36px' }}>Compare Africa's best travel insurance policies and receive your certificate instantly.</p>
            <div style={{ display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' }}>
              {isAgent
                ? <button className="btn btn--gold btn--lg" onClick={() => onNavigate('dashboard')}>Open Dashboard →</button>
                : <button className="btn btn--gold btn--lg" onClick={() => document.getElementById('policy-showcase')?.scrollIntoView({ behavior: 'smooth' })}>Compare Policies Now →</button>
              }
              <button className="btn btn--ghost btn--lg" onClick={() => onNavigate('verify')}>Verify a Certificate</button>
            </div>
          </div>
        </div>
      </section>

      {/* Chat bubble */}
      <button
        aria-label="Open live chat"
        onClick={() => onNavigate('catalog')}
        style={{ position: 'fixed', bottom: compareSelected.length >= 2 ? 88 : 28, right: 28, zIndex: 800, width: 52, height: 52, borderRadius: '50%', background: 'var(--indigo)', border: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, cursor: 'pointer', boxShadow: '0 4px 20px rgba(49,99,49,0.5)', transition: 'bottom 0.3s ease, transform 0.2s' }}
        onMouseEnter={e => e.currentTarget.style.transform = 'scale(1.1)'}
        onMouseLeave={e => e.currentTarget.style.transform = 'none'}
      >💬</button>
    </div>
  );
};

export default LandingPage;
