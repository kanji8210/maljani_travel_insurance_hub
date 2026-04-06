import React, { useState, useMemo, useEffect } from 'react';
import { useQuery, useMutation } from 'urql';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';

/* ─── GraphQL ──────────────────────────────────────────────────────────────── */
const GET_REGIONS = `
  query GetRegions {
    regions(first: 50) {
      nodes { name slug }
    }
  }
`;

const GET_POLICIES = `
  query GetPoliciesForQuote {
    policies(first: 100) {
      nodes {
        id
        databaseId
        title
        excerpt
        policyDescription
        policyCurrency
        policyInsurerName
        policyCountries
        policyDayPremiums { from to premium }
        regions { nodes { name slug } }
      }
    }
  }
`;

const SUBMIT_SALE = `
  mutation SubmitSale($input: SubmitPolicySaleInput!) {
    submitPolicySale(input: $input) {
      saleId
      policyNumber
      amountPaid
    }
  }
`;

/* ─── Helpers ──────────────────────────────────────────────────────────────── */
const CURRENCY = 'KES';

/** Days between two YYYY-MM-DD strings (inclusive) */
const tripDays = (dep, ret) => {
  if (!dep || !ret) return 0;
  const ms = new Date(ret) - new Date(dep);
  return Math.max(1, Math.round(ms / 86400000) + 1);
};

/** Find premium for given trip length from bracket array */
const bracketPremium = (brackets, days) => {
  if (!brackets || !brackets.length) return null;
  const match = brackets.find(b => days >= b.from && days <= b.to);
  return match ? match.premium : null;
};

const fmt = (n) =>
  n === null || n === undefined
    ? '—'
    : `${CURRENCY} ${Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const stripHtml = (s) => (s || '').replace(/<[^>]*>/g, ' ').replace(/\s{2,}/g, ' ').trim();

/** Does this policy cover the given destination region slug? */
const coversRegion = (policy, regionSlug) => {
  if (!regionSlug) return true;
  return (policy.regions?.nodes || []).some(r => r.slug === regionSlug);
};

/* ─── Shared field style ───────────────────────────────────────────────────── */
const fieldStyle = {
  display: 'flex', flexDirection: 'column', gap: 6,
};
const labelStyle = {
  fontSize: 11, fontWeight: 700, letterSpacing: '0.08em',
  textTransform: 'uppercase', color: 'var(--gold)',
};
const inputStyle = {
  padding: '10px 14px', borderRadius: 8, border: '1px solid var(--glass-border)',
  background: 'rgba(255,255,255,0.05)', color: '#fff', fontSize: 14,
  fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box',
};

/* ─── Step indicators ─────────────────────────────────────────────────────── */
const STEPS = ['Trip Details', 'Choose Plan', 'Your Info', 'Confirmed'];

const StepBar = ({ step }) => (
  <div style={{ display: 'flex', alignItems: 'center', gap: 0, marginBottom: 28 }}>
    {STEPS.map((label, i) => {
      const active  = i + 1 === step;
      const done    = i + 1 < step;
      return (
        <React.Fragment key={i}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4, flex: 1 }}>
            <div style={{
              width: 28, height: 28, borderRadius: '50%', display: 'flex',
              alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 800,
              background: done ? 'var(--gold)' : active ? 'var(--indigo)' : 'var(--glass-bg)',
              border: `2px solid ${done || active ? (done ? 'var(--gold)' : 'var(--indigo)') : 'var(--glass-border)'}`,
              color: done || active ? '#fff' : 'var(--slate)',
            }}>
              {done ? '✓' : i + 1}
            </div>
            <span style={{ fontSize: 10, color: active ? '#fff' : 'var(--slate)', fontWeight: active ? 700 : 400, whiteSpace: 'nowrap' }}>
              {label}
            </span>
          </div>
          {i < STEPS.length - 1 && (
            <div style={{ flex: 1, height: 2, background: done ? 'var(--gold)' : 'var(--glass-border)', marginBottom: 20, maxWidth: 40 }} />
          )}
        </React.Fragment>
      );
    })}
  </div>
);

/* ─── Component ───────────────────────────────────────────────────────────── */
const QuoteWizard = ({ initialPolicyId = null, initialSearchData = null, initialStep = 1, onNavigate }) => {
  const { user, loading: authLoading, role, login, register, error: authError } = useAuth();
  const { mobile } = useResponsive();
  const today = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0];

  const [step, setStep] = useState(initialStep ?? 1);
  const [form, setForm] = useState({
    originCountry:     'Kenya',
    destinationRegion: initialSearchData?.region || '',
    departure:         initialSearchData?.departure || today,
    returnDate:        initialSearchData?.returnDate || nextWeek,
    passengers:        initialSearchData?.passengers || 1,
    selectedPolicy:    null,
    name:              '',
    email:             '',
    phone:             '',
    dob:               '',
    passport:          '',
    password:          '',
    acceptTerms:       false,
  });

  const [{ data: regData }] = useQuery({ query: GET_REGIONS });
  const [{ data, fetching, error }] = useQuery({ query: GET_POLICIES });
  const [saleResult, submitSale] = useMutation(SUBMIT_SALE);
  const [accountCreated, setAccountCreated] = useState(false);
  const [isLoginMode, setIsLoginMode] = useState(false);
  const [regFetching, setRegFetching] = useState(false);

  // Sync initial search data on mount or change
  useEffect(() => {
    // 1. Priority: searchData from props (passed from Hero or Landing)
    if (initialSearchData) {
      setForm(f => ({
        ...f,
        destinationRegion: initialSearchData.region || f.destinationRegion,
        departure:         initialSearchData.departure || f.departure,
        returnDate:        initialSearchData.returnDate || f.returnDate,
        passengers:        initialSearchData.passengers || f.passengers,
      }));
    } 
    // 2. Fallback: check localStorage for a recent search
    else {
      try {
        const last = localStorage.getItem('maljani_last_search');
        if (last) {
          const parsed = JSON.parse(last);
          setForm(f => ({ ...f, ...parsed }));
        }
      } catch(e) { console.error('Last search parse error:', e); }
    }
  }, [initialSearchData]);

  // Persist search data when it changes (only the core search fields)
  useEffect(() => {
    const searchData = {
      destinationRegion:     form.destinationRegion,
      destinationRegionName: selectedRegionName,
      departure:             form.departure,
      returnDate:            form.returnDate,
      passengers:            form.passengers,
    };
    // Only save if at least region or dates are set
    if (searchData.destinationRegion || searchData.departure !== today) {
      localStorage.setItem('maljani_last_search', JSON.stringify(searchData));
    }
  }, [form.destinationRegion, form.departure, form.returnDate, form.passengers, today]);

  // Sync initial policy and smart jump
  useEffect(() => {
    if (data?.policies?.nodes && initialPolicyId) {
      const match = data.policies.nodes.find(p => String(p.databaseId) === String(initialPolicyId));
      if (match) {
        setForm(f => ({ ...f, selectedPolicy: match }));
        // If we also had search data, jump straight to the details step
        if (initialSearchData?.departure && initialSearchData?.returnDate) {
          console.log('QuoteWizard: Skipping to Step 3');
          setStep(3);
        }
      }
    }
  }, [data, initialPolicyId, initialSearchData?.departure, initialSearchData?.returnDate]);

  // Auto-fill personal info for logged-in users
  useEffect(() => {
    if (user && !authLoading) { // Wait until AuthContext loading state is finishized
      setForm(f => {
        // Only update fields that are currently empty to avoid overwriting user input
        const next = { ...f };
        let changed = false;
        
        if (!next.name && user.name)   { next.name = user.name; changed = true; }
        if (!next.email && user.email) { next.email = user.email; changed = true; }
        if (!next.phone && user.phone) { next.phone = user.phone; changed = true; }
        
        return changed ? next : f;
      });
    }
  }, [user, authLoading, role, step]); // Re-check when step changes or user data arrives

  // Store only IDs so comparisons are never affected by object reference or type mismatches
  const [showDateEdit, setShowDateEdit] = useState(false);

  const [compareIds, setCompareIds] = useState(new Set());
  const toggleCompare = (databaseId) => {
    const id = String(databaseId);
    setCompareIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else if (next.size < 3) next.add(id);
      return next;
    });
  };

  const isLoggedIn = !!user;
  const isClientAccount = role === 'client' && isLoggedIn;
  const effectiveName = user?.name || form.name;
  const effectiveEmail = user?.email || form.email;
  const accountEmail = user?.email || form.email;
  const accountName = user?.name || form.name;
  const shouldAskName = isLoggedIn && !effectiveName;
  const shouldAskEmail = isLoggedIn && !effectiveEmail;

  const set = (key, val) => setForm(f => ({ ...f, [key]: val }));

  const days = tripDays(form.departure, form.returnDate);

  const regions = regData?.regions?.nodes || [];
  const selectedRegionName = regions.find(r => r.slug === form.destinationRegion)?.name || '';

  /* Filter + annotate policies */
  const eligiblePolicies = useMemo(() => {
    if (!data?.policies?.nodes) return [];
    return data.policies.nodes
      .filter(p => coversRegion(p, form.destinationRegion))
      .map(p => ({
        ...p,
        computedPremium: bracketPremium(p.policyDayPremiums, days),
      }))
      .sort((a, b) => (a.computedPremium ?? Infinity) - (b.computedPremium ?? Infinity));
  }, [data, form.destinationRegion, days]);

  // Always derive compareList from eligiblePolicies so computedPremium is always current
  const compareList = eligiblePolicies.filter(p => compareIds.has(String(p.databaseId)));

  const totalPremium = form.selectedPolicy
    ? (bracketPremium(form.selectedPolicy.policyDayPremiums, days) ?? null) * form.passengers
    : null;

  const saleData = saleResult.data?.submitPolicySale;
  const saleId = saleData?.saleId;
  const selectedPolicyId = form.selectedPolicy?.databaseId;
  useEffect(() => {
    if (step !== 4) return;
    if (!saleId) {
      setStep(selectedPolicyId ? 3 : 1);
    }
  }, [step, saleId, selectedPolicyId]);

  const handlePurchase = async () => {
    // 1. If guest, register or login first
    if (!user) {
      if (isLoginMode) {
        console.log('QuoteWizard: Guest logging in...');
        setRegFetching(true);
        const logRes = await login(form.email, form.password);
        setRegFetching(false);
        if (!logRes.success) return; // Error handled by AuthContext
        console.log('QuoteWizard: Login successful.');
      } else {
        if (!form.password) {
          alert('Please create a password for your account.');
          return;
        }
        if (!form.acceptTerms) {
          alert('Please accept the terms and conditions.');
          return;
        }

        console.log('QuoteWizard: Guest purchasing. Attempting registration first...');
        setRegFetching(true);
        const reg = await register({
          fullName:    form.name,
          email:       form.email,
          password:    form.password,
          phone:       form.phone,
          accountType: 'insured'
        });
        setRegFetching(false);

        if (!reg.success) {
          // If error is "account exists", maybe suggest switching to login mode
          if (authError?.toLowerCase().includes('already registered')) {
            setIsLoginMode(true);
          }
          return;
        }
        console.log('QuoteWizard: Registration successful. Proceeding to sale...');
      }
    }

    // 2. Submit sale
    try {
      const result = await submitSale({
        input: {
          policyId:       parseInt(form.selectedPolicy.databaseId),
          passengers:     form.passengers,
          insuredNames:   effectiveName,
          insuredEmail:   effectiveEmail,
          insuredPhone:   form.phone,
          passportNumber: form.passport,
          insuredDob:     form.dob,
          departure:      form.departure,
          return:         form.returnDate,
        },
      });
  
      if (result.data?.submitPolicySale?.saleId) {
        setStep(4);
      } else if (result.error) {
        console.error('QuoteWizard: Mutation error:', result.error);
        // Step 3 button already displays saleResult.error.message
      }
    } catch (err) {
      console.error('QuoteWizard: Submission failed catch:', err);
    }
  };

  /* ── Step 1 — Trip Details ── */
  if (step === 1) return (
    <div className="glass-card fade-in" style={{ padding: '2rem' }}>
      <StepBar step={1} />
      <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 800, marginBottom: 24 }}>
        Your Trip <span style={{ color: 'var(--gold)' }}>Details</span>
      </h3>

      <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: 16, marginBottom: 16 }}>
        <div style={fieldStyle}>
          <label style={labelStyle}>Origin Country</label>
          <input style={inputStyle} placeholder="e.g. Kenya"
            value={form.originCountry}
            onChange={e => set('originCountry', e.target.value)} />
        </div>
        <div style={{ ...fieldStyle, gridColumn: '1 / -1' }}>
          <label style={labelStyle}>Destination Region *</label>
          {regions.length === 0 ? (
            <p style={{ color: 'var(--slate)', fontSize: 13 }}>Loading regions…</p>
          ) : (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {regions.map(r => {
                const active = form.destinationRegion === r.slug;
                return (
                  <button key={r.slug} type="button"
                    onClick={() => set('destinationRegion', active ? '' : r.slug)}
                    style={{
                      padding: '8px 18px', borderRadius: 100, fontSize: 13, fontWeight: 700,
                      cursor: 'pointer', border: `2px solid ${active ? 'var(--gold)' : 'var(--glass-border)'}`,
                      background: active ? 'rgba(212,175,55,0.15)' : 'rgba(255,255,255,0.04)',
                      color: active ? 'var(--gold)' : 'rgba(255,255,255,0.75)',
                      transition: 'border-color 0.15s, background 0.15s, color 0.15s',
                    }}>
                    {r.name}
                  </button>
                );
              })}
            </div>
          )}
        </div>
        <div style={fieldStyle}>
          <label style={labelStyle}>Departure Date</label>
          <input type="date" style={inputStyle} min={today}
            value={form.departure}
            onChange={e => set('departure', e.target.value)} />
        </div>
        <div style={fieldStyle}>
          <label style={labelStyle}>Return Date</label>
          <input type="date" style={inputStyle} min={form.departure}
            value={form.returnDate}
            onChange={e => set('returnDate', e.target.value)} />
        </div>
      </div>

      {/* Trip duration indicator */}
      {days > 0 && (
        <div style={{ padding: '10px 14px', background: 'rgba(49,99,49,0.12)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 8, fontSize: 13, marginBottom: 16, color: 'rgba(255,255,255,0.8)' }}>
          Trip duration: <strong style={{ color: 'var(--gold)' }}>{days} day{days !== 1 ? 's' : ''}</strong>
          {form.originCountry && selectedRegionName && (
            <> &nbsp;·&nbsp; {form.originCountry} → {selectedRegionName}</>
          )}
        </div>
      )}

      <div style={fieldStyle}>
        <label style={labelStyle}>Number of Travellers</label>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button style={{ ...inputStyle, width: 40, textAlign: 'center', cursor: 'pointer', flexShrink: 0 }}
            onClick={() => set('passengers', Math.max(1, form.passengers - 1))}>−</button>
          <span style={{ fontSize: 18, fontWeight: 800, minWidth: 24, textAlign: 'center' }}>{form.passengers}</span>
          <button style={{ ...inputStyle, width: 40, textAlign: 'center', cursor: 'pointer', flexShrink: 0 }}
            onClick={() => set('passengers', form.passengers + 1)}>+</button>
        </div>
      </div>

      <button
        style={{ marginTop: 24, width: '100%', padding: '13px', borderRadius: 8, background: 'var(--indigo)', border: 'none', color: '#fff', fontSize: 14, fontWeight: 800, cursor: !form.destinationRegion ? 'not-allowed' : 'pointer', opacity: !form.destinationRegion ? 0.5 : 1, letterSpacing: '0.05em' }}
        disabled={!form.destinationRegion}
        onClick={() => setStep(2)}>
        View Available Plans →
      </button>
    </div>
  );

  /* ── Step 2 — Choose Plan ── */
  if (step === 2) return (
    <div className="glass-card fade-in" style={{ padding: '2rem' }}>
      <StepBar step={2} />
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16, flexWrap: 'wrap', gap: 8 }}>
        <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 800, margin: 0 }}>
          Available <span style={{ color: 'var(--gold)' }}>Plans</span>
        </h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4, textAlign: 'right' }}>
          <div style={{ fontSize: 12, color: 'var(--slate)' }}>
            {selectedRegionName} · {days} day{days !== 1 ? 's' : ''} · {form.passengers} traveller{form.passengers !== 1 ? 's' : ''} · Prices in {CURRENCY}
          </div>
          <button
            type="button"
            onClick={() => setShowDateEdit(v => !v)}
            style={{ alignSelf: 'flex-end', fontSize: 11, fontWeight: 700, color: 'var(--gold)', background: 'rgba(212,175,55,0.1)', border: '1px solid rgba(212,175,55,0.3)', borderRadius: 100, padding: '4px 12px', cursor: 'pointer', letterSpacing: '0.04em' }}>
            {showDateEdit ? '✕ Close' : '✏ Adjust Dates'}
          </button>
        </div>
      </div>

      {/* ── Inline date / traveller editor ── */}
      {showDateEdit && (
        <div style={{ marginBottom: 20, padding: '16px 18px', borderRadius: 10, background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(212,175,55,0.25)' }}>
          <p style={{ margin: '0 0 12px', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: 'var(--gold)' }}>Adjust Trip Dates</p>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr auto', gap: 12, alignItems: 'flex-end' }}>
            <div style={fieldStyle}>
              <label style={labelStyle}>Departure</label>
              <input type="date" style={inputStyle} min={today}
                value={form.departure}
                onChange={e => {
                  const dep = e.target.value;
                  set('departure', dep);
                  // Push return date forward if it would be before departure
                  if (form.returnDate < dep) set('returnDate', dep);
                }} />
            </div>
            <div style={fieldStyle}>
              <label style={labelStyle}>Return</label>
              <input type="date" style={inputStyle} min={form.departure}
                value={form.returnDate}
                onChange={e => set('returnDate', e.target.value)} />
            </div>
            <div style={{ paddingBottom: 2, textAlign: 'center', minWidth: 80 }}>
              <div style={{ fontSize: 20, fontWeight: 800, color: 'var(--gold)' }}>{days}</div>
              <div style={{ fontSize: 10, color: 'var(--slate)', textTransform: 'uppercase', letterSpacing: '0.07em' }}>day{days !== 1 ? 's' : ''}</div>
            </div>
          </div>
        </div>
      )}

      {fetching && <p style={{ color: 'var(--slate)', textAlign: 'center', padding: '2rem 0' }}>Loading plans…</p>}
      {error   && <p style={{ color: '#f87171' }}>Error: {error.message}</p>}

      {/* ── Comparison panel ── */}
      {compareList.length >= 2 && (
        <div style={{ marginBottom: 24, borderRadius: 10, overflow: 'hidden', border: '1px solid rgba(212,175,55,0.35)' }}>
          <div style={{ padding: '10px 16px', background: 'rgba(212,175,55,0.1)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontWeight: 800, fontSize: 13, color: 'var(--gold)' }}>Comparing {compareList.length} Plans</span>
            <button onClick={() => setCompareIds(new Set())}
              style={{ fontSize: 11, color: 'var(--slate)', background: 'none', border: 'none', cursor: 'pointer', padding: '2px 8px' }}>
              Clear all
            </button>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13, minWidth: 420 }}>
              <thead>
                <tr>
                  <th style={{ padding: '10px 14px', textAlign: 'left', color: 'var(--slate)', fontSize: 11, fontWeight: 700, borderBottom: '1px solid var(--glass-border)', width: 100 }} />
                  {compareList.map(p => (
                    <th key={p.databaseId} style={{ padding: '10px 14px', textAlign: 'left', color: '#fff', fontWeight: 800, borderBottom: '1px solid var(--glass-border)', borderLeft: '1px solid var(--glass-border)', minWidth: 160 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 6 }}>
                        <span>{p.title}</span>
                        <button type="button" onClick={e => { e.stopPropagation(); e.preventDefault(); toggleCompare(p.databaseId); }}
                          style={{ fontSize: 14, color: 'rgba(255,255,255,0.5)', background: 'none', border: 'none', cursor: 'pointer', flexShrink: 0, lineHeight: 1, padding: '0 2px' }}>✕</button>
                      </div>
                      {p.policyInsurerName && <div style={{ fontSize: 10, color: 'var(--slate)', fontWeight: 400, marginTop: 2 }}>{p.policyInsurerName}</div>}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[
                  { label: 'Total Price', render: p => {
                    const tot = p.computedPremium !== null ? p.computedPremium * form.passengers : null;
                    return tot !== null
                      ? <strong style={{ color: 'var(--gold)', fontSize: 15 }}>{fmt(tot)}</strong>
                      : <span style={{ color: 'var(--slate)' }}>On request</span>;
                  }},
                  { label: 'Per Person', render: p => p.computedPremium !== null ? fmt(p.computedPremium) : '—' },
                  { label: 'Region', render: p => (p.regions?.nodes || []).map(r => r.name).join(', ') || '—' },
                  { label: 'Countries', render: p => {
                    const list = p.policyCountries || [];
                    if (!list.length) return '—';
                    return list.slice(0, 5).join(', ') + (list.length > 5 ? ` +${list.length - 5}` : '');
                  }},
                  { label: 'About', render: p => stripHtml(p.policyDescription || p.excerpt || '').substring(0, 90) + '…' },
                ].map(row => (
                  <tr key={row.label} style={{ borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                    <td style={{ padding: '9px 14px', fontSize: 11, fontWeight: 700, color: 'var(--slate)', textTransform: 'uppercase', letterSpacing: '0.07em', whiteSpace: 'nowrap' }}>{row.label}</td>
                    {compareList.map(p => (
                      <td key={p.databaseId} style={{ padding: '9px 14px', color: 'rgba(255,255,255,0.85)', borderLeft: '1px solid var(--glass-border)', lineHeight: 1.5 }}>
                        {row.render(p)}
                      </td>
                    ))}
                  </tr>
                ))}
                <tr>
                  <td style={{ padding: '10px 14px' }} />
                  {compareList.map(p => (
                    <td key={p.databaseId} style={{ padding: '10px 14px', borderLeft: '1px solid var(--glass-border)' }}>
                      <button
                        onClick={() => { set('selectedPolicy', p); setCompareIds(new Set()); setStep(3); }}
                        style={{ padding: '8px 0', borderRadius: 7, border: 'none', background: 'var(--indigo)', color: '#fff', fontSize: 12, fontWeight: 800, cursor: 'pointer', width: '100%' }}>
                        Select &amp; Continue →
                      </button>
                    </td>
                  ))}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {compareList.length === 1 && (
        <div style={{ padding: '8px 14px', borderRadius: 7, background: 'rgba(49,99,49,0.08)', border: '1px solid rgba(49,99,49,0.2)', fontSize: 12, color: 'rgba(255,255,255,0.6)', marginBottom: 16 }}>
          Add one more plan to compare side-by-side.
        </div>
      )}

      {!fetching && !error && eligiblePolicies.length === 0 && (
        <div style={{ textAlign: 'center', padding: '2rem 0' }}>
          <p style={{ color: 'var(--slate)', marginBottom: 16 }}>No plans found for <strong>{selectedRegionName}</strong>.</p>
          <button style={{ padding: '9px 20px', borderRadius: 7, border: '1px solid var(--glass-border)', background: 'none', color: '#fff', cursor: 'pointer', fontSize: 13 }}
            onClick={() => setStep(1)}>← Adjust Trip Details</button>
        </div>
      )}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {eligiblePolicies.map(policy => {
          const prem = policy.computedPremium;
          const total = prem !== null ? prem * form.passengers : null;
          const selected = form.selectedPolicy?.databaseId === policy.databaseId;
          const inCompare = compareIds.has(String(policy.databaseId));
          return (
            <div key={policy.id}
              onClick={() => set('selectedPolicy', policy)}
              style={{
                padding: '16px 18px', borderRadius: 10, cursor: 'pointer',
                border: `2px solid ${selected ? 'var(--gold)' : inCompare ? 'rgba(49,99,49,0.5)' : 'var(--glass-border)'}`,
                background: selected ? 'rgba(212,175,55,0.07)' : inCompare ? 'rgba(49,99,49,0.06)' : 'var(--glass-bg)',
                transition: 'border-color 0.15s, background 0.15s',
              }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                    {selected && <span style={{ fontSize: 14, color: 'var(--gold)' }}>✓</span>}
                    <strong style={{ fontSize: 15, color: selected ? 'var(--gold)' : '#fff' }}>{policy.title}</strong>
                  </div>
                  {policy.policyInsurerName && (
                    <p style={{ fontSize: 11, color: 'var(--slate)', margin: '0 0 6px', textTransform: 'uppercase', letterSpacing: '0.06em' }}>{policy.policyInsurerName}</p>
                  )}
                  <p style={{ fontSize: 12, color: 'var(--slate)', lineHeight: 1.5, margin: 0 }}>
                    {stripHtml(policy.policyDescription || policy.excerpt || '').substring(0, 110)}…
                  </p>
                </div>
                <div style={{ textAlign: 'right', flexShrink: 0, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 6 }}>
                  {prem !== null ? (
                    <>
                      <div style={{ fontSize: 18, fontWeight: 800, color: 'var(--gold)' }}>{fmt(total)}</div>
                      <div style={{ fontSize: 10, color: 'var(--slate)' }}>{fmt(prem)} × {form.passengers}</div>
                    </>
                  ) : (
                    <div style={{ fontSize: 12, color: 'var(--slate)' }}>Quote on request</div>
                  )}
                  <button
                    type="button"
                    onClick={e => { e.stopPropagation(); e.preventDefault(); toggleCompare(policy.databaseId); }}
                    style={{
                      padding: '5px 13px', borderRadius: 100, fontSize: 11, fontWeight: 700, cursor: 'pointer',
                      border: inCompare ? '1.5px solid #4ade80' : '1.5px solid rgba(255,255,255,0.25)',
                      background: inCompare ? 'rgba(49,99,49,0.25)' : 'rgba(255,255,255,0.07)',
                      color: inCompare ? '#86efac' : 'rgba(255,255,255,0.85)',
                      transition: 'all 0.15s',
                      whiteSpace: 'nowrap',
                    }}>
                    {inCompare ? '✓ Comparing' : '＋ Compare'}
                  </button>
                </div>
              </div>
              {policy.policyCountries?.length > 0 && (
                <div style={{ marginTop: 10, display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                  {policy.policyCountries.slice(0, 6).map(c => (
                    <span key={c} style={{ fontSize: 10, padding: '2px 8px', borderRadius: 100, background: 'rgba(49,99,49,0.15)', border: '1px solid rgba(49,99,49,0.25)', color: '#86efac' }}>{c}</span>
                  ))}
                  {policy.policyCountries.length > 6 && (
                    <span style={{ fontSize: 10, color: 'var(--slate)' }}>+{policy.policyCountries.length - 6} more</span>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 20 }}>
        <button style={{ padding: '11px', borderRadius: 8, border: '1px solid var(--glass-border)', background: 'none', color: '#fff', cursor: 'pointer', fontSize: 13, fontWeight: 700 }}
          onClick={() => { setStep(1); setCompareIds(new Set()); }}>← Back</button>
        <button style={{ padding: '11px', borderRadius: 8, border: 'none', background: form.selectedPolicy ? 'var(--indigo)' : 'var(--glass-bg)', color: '#fff', cursor: form.selectedPolicy ? 'pointer' : 'not-allowed', opacity: form.selectedPolicy ? 1 : 0.5, fontSize: 13, fontWeight: 800 }}
          disabled={!form.selectedPolicy}
          onClick={() => setStep(3)}>Continue →</button>
      </div>
    </div>
  );

  /* ── Step 3 — Personal Details ── */
  if (step === 3) {
    const prem  = bracketPremium(form.selectedPolicy?.policyDayPremiums, days);
    const total = prem !== null ? prem * form.passengers : null;
    return (
      <div className="glass-card fade-in" style={{ padding: '2rem' }}>
        <StepBar step={3} />
        <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 800, marginBottom: 6 }}>
          Your <span style={{ color: 'var(--gold)' }}>Details</span>
        </h3>

        {/* Summary strip — editable dates */}
        <div style={{ padding: '14px 16px', background: 'rgba(212,175,55,0.08)', border: '1px solid rgba(212,175,55,0.25)', borderRadius: 8, marginBottom: 22 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10, flexWrap: 'wrap', gap: 6 }}>
            <strong style={{ fontSize: 14 }}>{form.selectedPolicy?.title}</strong>
            {total !== null && <span style={{ fontWeight: 800, color: 'var(--gold)', fontSize: 15 }}>{fmt(total)}</span>}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr auto', gap: 10, alignItems: 'flex-end' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <label style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: 'rgba(255,255,255,0.5)' }}>Departure</label>
              <input
                type="date"
                min={today}
                value={form.departure}
                style={{ padding: '7px 10px', borderRadius: 7, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.06)', color: '#fff', fontSize: 12, fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box' }}
                onChange={e => {
                  const dep = e.target.value;
                  set('departure', dep);
                  if (form.returnDate < dep) set('returnDate', dep);
                }}
              />
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <label style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: 'rgba(255,255,255,0.5)' }}>Return</label>
              <input
                type="date"
                min={form.departure}
                value={form.returnDate}
                style={{ padding: '7px 10px', borderRadius: 7, border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.06)', color: '#fff', fontSize: 12, fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box' }}
                onChange={e => set('returnDate', e.target.value)}
              />
            </div>
            <div style={{ textAlign: 'center', paddingBottom: 2 }}>
              <div style={{ fontSize: 18, fontWeight: 800, color: 'var(--gold)', lineHeight: 1 }}>{days}</div>
              <div style={{ fontSize: 9, color: 'var(--slate)', textTransform: 'uppercase', letterSpacing: '0.07em' }}>day{days !== 1 ? 's' : ''}</div>
            </div>
          </div>
          <div style={{ marginTop: 8, fontSize: 11, color: 'var(--slate)' }}>
            {selectedRegionName} · {form.passengers} traveller{form.passengers !== 1 ? 's' : ''}
          </div>
        </div>

        {/* Pre-fill notice for logged-in users */}
        {isLoggedIn && (
          <div style={{
            marginBottom: 20,
            padding: '16px 18px',
            background: 'rgba(59,130,246,0.08)',
            border: '1px solid rgba(59,130,246,0.3)',
            borderRadius: 10,
            fontSize: 12,
            color: 'rgba(255,255,255,0.88)',
            lineHeight: 1.6,
          }}>
            <div style={{ fontWeight: 700, marginBottom: 8 }}>Account details are locked.</div>
            <div>
              Notifications will be sent to <strong>{accountEmail || 'your account email'}</strong>.
            </div>
            {accountName ? (
              <div style={{ marginTop: 8 }}>
                Policy applicant name on file: <strong>{accountName}</strong>.
              </div>
            ) : (
              <div style={{ marginTop: 8, color: 'rgba(255,255,255,0.75)' }}>
                Your account is missing a name. Please provide the full legal name below.
              </div>
            )}
            {!accountEmail && (
              <div style={{ marginTop: 8, color: 'rgba(255,255,255,0.75)' }}>
                Your account is missing an email address. Please provide one for notifications.
              </div>
            )}
            <div style={{ marginTop: 12, fontSize: 11, color: 'rgba(255,255,255,0.72)' }}>
              If you need to change the email or name on file, update your profile before purchasing.
            </div>
          </div>
        )}

        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {[
            ...(shouldAskName ? [{ key: 'name', label: 'Full Legal Name', type: 'text', placeholder: 'As on passport' }] : []),
            ...(shouldAskEmail ? [{ key: 'email', label: 'Email Address', type: 'email', placeholder: 'you@example.com' }] : []),
            { key: 'phone',    label: 'Phone Number',       type: 'tel',   placeholder: '+254 700 000 000' },
            { key: 'dob',      label: 'Date of Birth',      type: 'date',  placeholder: '' },
            { key: 'passport', label: 'Passport / ID No.',  type: 'text',  placeholder: 'Optional' },
          ].map(({ key, label, type, placeholder }) => (
            <div key={key} style={fieldStyle}>
              <label style={labelStyle}>{label}</label>
              <input
                type={type}
                style={inputStyle}
                placeholder={placeholder}
                value={form[key]}
                onChange={e => set(key, e.target.value)}
              />
            </div>
          ))}

          {/* Guest Registration / Login Toggle Fields */}
          {!user && (
            <div style={{ marginTop: 10, padding: '20px', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--glass-border)', borderRadius: 10 }}>
              <div style={{ display: 'flex', gap: 20, marginBottom: 20, borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
                <button 
                  type="button" 
                  onClick={() => setIsLoginMode(false)}
                  style={{ 
                    padding: '8px 0', border: 'none', background: 'none', cursor: 'pointer',
                    fontSize: 13, fontWeight: 700, color: !isLoginMode ? 'var(--gold)' : 'var(--slate)',
                    borderBottom: !isLoginMode ? '2px solid var(--gold)' : '2px solid transparent',
                    transition: 'all 0.2s'
                  }}
                >NEW CUSTOMER</button>
                <button 
                  type="button" 
                  onClick={() => setIsLoginMode(true)}
                  style={{ 
                    padding: '8px 0', border: 'none', background: 'none', cursor: 'pointer',
                    fontSize: 13, fontWeight: 700, color: isLoginMode ? 'var(--gold)' : 'var(--slate)',
                    borderBottom: isLoginMode ? '2px solid var(--gold)' : '2px solid transparent',
                    transition: 'all 0.2s'
                  }}
                >ALREADY REGISTERED</button>
              </div>

              <div style={fieldStyle}>
                <label style={labelStyle}>{isLoginMode ? 'Account Password' : 'Create Password *'}</label>
                <input type="password" style={inputStyle} placeholder="••••••••"
                  value={form.password} onChange={e => set('password', e.target.value)} />
                {!isLoginMode && <p style={{ fontSize: 10, color: 'var(--slate)', marginTop: 4 }}>Used for your Maljani account login</p>}
              </div>
              
              {!isLoginMode && (
                <div style={{ ...fieldStyle, marginTop: 14 }}>
                  <label style={{ ...labelStyle, display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer', textTransform: 'none' }}>
                    <input type="checkbox" checked={form.acceptTerms} onChange={e => set('acceptTerms', e.target.checked)}
                      style={{ width: 18, height: 18, accentColor: 'var(--gold)' }} />
                    <span style={{ fontSize: 12, lineHeight: 1.4 }}>I agree to the <a href="/terms" target="_blank" style={{ color: 'var(--gold)' }}>Terms and Conditions</a> and Privacy Policy.</span>
                  </label>
                </div>
              )}
            </div>
          )}
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 22 }}>
          <button style={{ padding: '11px', borderRadius: 8, border: '1px solid var(--glass-border)', background: 'none', color: '#fff', cursor: 'pointer', fontSize: 13, fontWeight: 700 }}
            onClick={() => setStep(2)}>← Back</button>
          <button
            style={{ padding: '11px', borderRadius: 8, border: 'none', background: 'var(--gold)', color: '#0a0e27', cursor: (!effectiveName || !effectiveEmail || saleResult.fetching) ? 'not-allowed' : 'pointer', opacity: (!effectiveName || !effectiveEmail) ? 0.6 : 1, fontSize: 13, fontWeight: 800 }}
            disabled={!effectiveName || !effectiveEmail || saleResult.fetching || regFetching}
            onClick={handlePurchase}>
            {saleResult.fetching || regFetching ? (isLoginMode ? 'Signing in...' : 'Processing…') : 
              user ? `Submit Application ${total !== null ? '— ' + fmt(total) : ''}` : 
              (isLoginMode ? `Login & Submit ${total !== null ? '— ' + fmt(total) : ''}` : `Register & Submit ${total !== null ? '— ' + fmt(total) : ''}`)
            }
          </button>
        </div>
        {saleResult.error && (
          <div style={{ marginTop: 10, padding: 12, background: 'rgba(240,68,68,0.1)', border: '1px solid rgba(240,68,68,0.2)', borderRadius: 8 }}>
            <p style={{ color: '#f87171', fontSize: 13, fontWeight: 700, margin: '0 0 4px' }}>Submission Error</p>
            <p style={{ color: '#fca5a5', fontSize: 12, margin: 0, whiteSpace: 'pre-wrap', fontFamily: 'monospace' }}>
              {saleResult.error.message}
              {saleResult.error.graphQLErrors?.map(e => e.message).join('\n')}
            </p>
          </div>
        )}
        {authError && !user && (
          <div style={{ 
            marginTop: 16, padding: '12px 16px', borderRadius: 8, 
            background: 'rgba(248,113,113,0.1)', border: '1px solid rgba(248,113,113,0.3)',
            fontSize: 13, color: '#fca5a5'
          }}>
            <div style={{ fontWeight: 800, marginBottom: 4 }}>
              {authError.toLowerCase().includes('already registered') ? 'Account Exists' : 'Authentication Error'}
            </div>
            <p style={{ margin: 0, opacity: 0.9 }}>
              {authError.toLowerCase().includes('already registered') 
                ? <>The email <strong>{form.email}</strong> is already registered. Please click <strong style={{ color: 'var(--gold)', cursor: 'pointer' }} onClick={() => setIsLoginMode(true)}>Already Registered</strong> above to log in.</>
                : authError
              }
            </p>
          </div>
        )}
        {!saleResult.fetching && !saleResult.error && !saleResult.data?.submitPolicySale?.saleId && saleResult.data && (
          <p style={{ color: '#f87171', fontSize: 12, marginTop: 10 }}>Purchase could not be recorded. Please try again or contact support.</p>
        )}
      </div>
    );
  }

  /* ── Step 4 — Confirmed ── */
  if (step === 4) {
    return (
      <div className="glass-card fade-in">
        <div style={{ textAlign: 'center', padding: '40px 20px' }}>
          <div style={{ width: 80, height: 80, borderRadius: '50%', background: 'rgba(34,197,94,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
            <span style={{ fontSize: 40, color: '#22c55e' }}>✓</span>
          </div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 32, marginBottom: 12, color: '#fff' }}>
            Application <span style={{ color: 'var(--gold)' }}>Received</span>
          </h2>
          <p style={{ color: 'var(--slate)', fontSize: 16, maxWidth: 400, margin: '0 auto 30px', lineHeight: 1.6 }}>
            Your booking for policy <strong style={{ color: '#fff' }}>{saleData?.policyNumber || '—'}</strong> has been registered. 
            It will be activated as soon as your payment is processed.
          </p>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 12, maxWidth: 300, margin: '0 auto' }}>
            <button 
              className="btn btn--primary" 
              style={{ width: '100%' }}
              onClick={() => onNavigate?.('dashboard')}
            >
              View in My Dashboard
            </button>
            <button 
              className="btn btn--ghost" 
              style={{ width: '100%', fontSize: 13 }}
              onClick={() => {
                setStep(1);
                setForm({
                  selectedPolicy: null,
                  days: 0,
                  name: '',
                  email: '',
                  phone: '',
                  passport: '',
                  countryOfOrigin: 'Kenya',
                  dob: '',
                  password: '',
                  acceptTerms: false
                });
              }}
            >
              Get Another Quote
            </button>
          </div>

          {accountCreated && (
            <div style={{ marginTop: 32, padding: '16px', background: 'rgba(34,197,94,0.05)', border: '1px solid rgba(34,197,94,0.2)', borderRadius: 12, fontSize: 13, textAlign: 'left' }}>
              <div style={{ fontWeight: 800, color: '#4ade80', marginBottom: 4 }}>Account Created Successfully</div>
              <p style={{ margin: 0, opacity: 0.8, lineHeight: 1.5 }}>
                You can now log in with <strong>{form.email}</strong> to track your applications and download certificates.
              </p>
            </div>
          )}
        </div>
      </div>
    );
  }
};

export default QuoteWizard;
