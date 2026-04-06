import React, { useState, useMemo } from 'react';
import { useMutation, gql } from 'urql';
import { useResponsive } from '../lib/useResponsive';

const UPDATE_POLICY_SALE = gql`
  mutation AssignClient(
    $saleId: Int!, $insuredNames: String, $insuredEmail: String,
    $insuredPhone: String
  ) {
    updatePolicySale(input: {
      saleId: $saleId, insuredNames: $insuredNames,
      insuredEmail: $insuredEmail, insuredPhone: $insuredPhone
    }) {
      saleId success
    }
  }
`;

const AssignClientModal = ({ policy, clients = [], onClose, onAssigned }) => {
  const { mobile } = useResponsive();
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [status, setStatus] = useState(null);
  const [errMsg, setErrMsg] = useState('');
  const [, executeMutation] = useMutation(UPDATE_POLICY_SALE);

  const filtered = useMemo(() => {
    if (!search.trim()) return clients;
    const q = search.toLowerCase();
    return clients.filter(c =>
      (c.name || '').toLowerCase().includes(q) ||
      (c.email || '').toLowerCase().includes(q) ||
      (c.phone || '').includes(q)
    );
  }, [clients, search]);

  const handleAssign = async () => {
    if (!selected) return;
    setStatus('saving');
    setErrMsg('');
    const res = await executeMutation({
      saleId: policy.id,
      insuredNames: selected.name,
      insuredEmail: selected.email,
      insuredPhone: selected.phone || undefined,
    });
    if (res.error) {
      setStatus('error');
      setErrMsg(res.error.message.replace(/\[GraphQL\]\s?/g, ''));
    } else {
      setStatus('success');
      setTimeout(() => { onAssigned?.(); onClose(); }, 900);
    }
  };

  const overlay = {
    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(6px)',
    display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '1rem',
  };
  const card = {
    background: 'var(--navy, #0f172a)', border: '1px solid var(--glass-border, rgba(255,255,255,0.1))',
    borderRadius: '18px', padding: mobile ? '1.5rem' : '2rem', width: '100%', maxWidth: 500,
    maxHeight: '85vh', display: 'flex', flexDirection: 'column', position: 'relative',
  };

  return (
    <div style={overlay} onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div style={card}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem', flexShrink: 0 }}>
          <div>
            <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.1rem', fontWeight: 700, margin: 0 }}>Assign to Client</h3>
            <p style={{ margin: '0.25rem 0 0', fontSize: '0.72rem', color: 'var(--text-muted)' }}>
              Policy <span style={{ color: 'var(--gold)' }}>{policy.policyNumber}</span> &middot; currently insured: <strong>{policy.insuredNames}</strong>
            </p>
          </div>
          <button onClick={onClose} style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer', fontSize: '1.4rem', padding: '0.2rem' }}>✕</button>
        </div>

        {/* Search */}
        <input
          value={search} onChange={(e) => setSearch(e.target.value)}
          placeholder="Search clients by name, email or phone…"
          style={{
            width: '100%', background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: '8px', padding: '0.6rem 0.85rem', color: 'white', fontSize: '0.82rem', outline: 'none',
            marginBottom: '0.75rem', flexShrink: 0,
          }}
        />

        {/* Client list */}
        <div style={{ flex: 1, overflowY: 'auto', marginBottom: '1rem' }}>
          {filtered.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '2rem 0', color: 'var(--text-muted)', fontSize: '0.8rem' }}>
              {clients.length === 0 ? 'No clients in your CRM yet' : 'No matches found'}
            </div>
          ) : filtered.map((c, i) => {
            const isActive = selected?.email === c.email;
            return (
              <div key={c.email || i} onClick={() => setSelected(c)} style={{
                padding: '0.65rem 0.85rem', borderRadius: '10px', cursor: 'pointer',
                background: isActive ? 'rgba(212,175,55,0.12)' : 'rgba(255,255,255,0.03)',
                border: isActive ? '1px solid rgba(212,175,55,0.4)' : '1px solid transparent',
                marginBottom: '0.4rem', transition: 'all 0.15s ease',
              }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <div style={{ fontWeight: 600, fontSize: '0.82rem' }}>{c.name}</div>
                    <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: '0.15rem' }}>
                      {c.email}{c.phone ? ` · ${c.phone}` : ''}
                    </div>
                  </div>
                  {isActive && (
                    <div style={{ color: 'var(--gold)', fontWeight: 800, fontSize: '0.85rem' }}>✓</div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Feedback */}
        {status === 'error' && <div style={{ color: '#f87171', fontSize: '0.78rem', fontWeight: 600, marginBottom: '0.5rem' }}>{errMsg}</div>}
        {status === 'success' && <div style={{ color: '#22c55e', fontSize: '0.78rem', fontWeight: 600, marginBottom: '0.5rem' }}>Client assigned successfully!</div>}

        {/* Actions */}
        <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end', flexShrink: 0 }}>
          <button onClick={onClose} style={{
            background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: '8px', padding: '0.55rem 1.2rem', color: 'white', cursor: 'pointer', fontSize: '0.78rem', fontWeight: 600,
          }}>Cancel</button>
          <button onClick={handleAssign} disabled={!selected || status === 'saving'} style={{
            background: selected ? 'linear-gradient(135deg, var(--gold, #d4af37), #b8941f)' : 'rgba(255,255,255,0.1)',
            border: 'none', borderRadius: '8px', padding: '0.55rem 1.5rem',
            color: selected ? '#0f172a' : 'var(--text-muted)', cursor: selected ? 'pointer' : 'default',
            fontSize: '0.78rem', fontWeight: 800, opacity: status === 'saving' ? 0.6 : 1,
          }}>
            {status === 'saving' ? 'Assigning…' : 'Assign Client'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default AssignClientModal;
