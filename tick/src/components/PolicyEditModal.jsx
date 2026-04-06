import React, { useState } from 'react';
import { useMutation, gql } from 'urql';
import { useResponsive } from '../lib/useResponsive';

const UPDATE_POLICY_SALE = gql`
  mutation UpdatePolicySale(
    $saleId: Int!, $departure: String, $return: String, $passengers: Int,
    $insuredNames: String, $insuredDob: String, $passportNumber: String,
    $nationalId: String, $insuredPhone: String, $insuredEmail: String,
    $insuredAddress: String, $countryOfOrigin: String
  ) {
    updatePolicySale(input: {
      saleId: $saleId, departure: $departure, return: $return, passengers: $passengers,
      insuredNames: $insuredNames, insuredDob: $insuredDob, passportNumber: $passportNumber,
      nationalId: $nationalId, insuredPhone: $insuredPhone, insuredEmail: $insuredEmail,
      insuredAddress: $insuredAddress, countryOfOrigin: $countryOfOrigin
    }) {
      saleId amountPaid success
    }
  }
`;

const PolicyEditModal = ({ policy, onClose, onSaved }) => {
  const { mobile } = useResponsive();
  const [form, setForm] = useState({
    insuredNames:   policy.insuredNames || '',
    insuredEmail:   policy.insuredEmail || '',
    insuredPhone:   policy.insuredPhone || '',
    insuredDob:     policy.insuredDob || '',
    passportNumber: policy.passportNumber || '',
    nationalId:     policy.nationalId || '',
    insuredAddress: policy.insuredAddress || '',
    countryOfOrigin:policy.countryOfOrigin || '',
    departure:      policy.departure || '',
    returnDate:     policy.returnDate || '',
    passengers:     policy.passengers || 1,
  });
  const [status, setStatus] = useState(null); // 'saving' | 'success' | 'error'
  const [errMsg, setErrMsg] = useState('');
  const [, executeMutation] = useMutation(UPDATE_POLICY_SALE);

  const set = (k) => (e) => setForm(prev => ({ ...prev, [k]: e.target.value }));

  const handleSave = async () => {
    setStatus('saving');
    setErrMsg('');
    const res = await executeMutation({
      saleId:         policy.id,
      departure:      form.departure || undefined,
      return:         form.returnDate || undefined,
      passengers:     parseInt(form.passengers, 10) || undefined,
      insuredNames:   form.insuredNames || undefined,
      insuredDob:     form.insuredDob || undefined,
      passportNumber: form.passportNumber || undefined,
      nationalId:     form.nationalId || undefined,
      insuredPhone:   form.insuredPhone || undefined,
      insuredEmail:   form.insuredEmail || undefined,
      insuredAddress: form.insuredAddress || undefined,
      countryOfOrigin:form.countryOfOrigin || undefined,
    });
    if (res.error) {
      setStatus('error');
      setErrMsg(res.error.message.replace(/\[GraphQL\]\s?/g, ''));
    } else {
      setStatus('success');
      setTimeout(() => { onSaved?.(); onClose(); }, 900);
    }
  };

  const overlay = {
    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(6px)',
    display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '1rem',
  };
  const card = {
    background: 'var(--navy, #0f172a)', border: '1px solid var(--glass-border, rgba(255,255,255,0.1))',
    borderRadius: '18px', padding: mobile ? '1.5rem' : '2rem', width: '100%', maxWidth: 560,
    maxHeight: '90vh', overflowY: 'auto', position: 'relative',
  };
  const labelStyle = { display: 'block', fontSize: '0.68rem', fontWeight: 700, color: 'var(--text-muted)', letterSpacing: '0.06em', marginBottom: '0.3rem' };
  const inputStyle = {
    width: '100%', background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
    borderRadius: '8px', padding: '0.55rem 0.8rem', color: 'white', fontSize: '0.84rem', outline: 'none',
  };
  const row = { display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: '1rem' };

  return (
    <div style={overlay} onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div style={card}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
          <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.2rem', fontWeight: 700, margin: 0 }}>
            Edit Policy <span style={{ color: 'var(--gold)', fontSize: '0.85rem' }}>{policy.policyNumber}</span>
          </h3>
          <button onClick={onClose} style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer', fontSize: '1.4rem', padding: '0.2rem' }}>✕</button>
        </div>

        {/* Client Info */}
        <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'var(--gold)', letterSpacing: '0.1em', marginBottom: '0.75rem' }}>CLIENT INFORMATION</div>
        <div style={row}>
          <div><label style={labelStyle}>LEGAL NAME *</label><input style={inputStyle} value={form.insuredNames} onChange={set('insuredNames')} placeholder="Full legal name" /></div>
          <div><label style={labelStyle}>EMAIL</label><input style={inputStyle} value={form.insuredEmail} onChange={set('insuredEmail')} type="email" /></div>
        </div>
        <div style={{ ...row, marginTop: '0.75rem' }}>
          <div><label style={labelStyle}>PHONE</label><input style={inputStyle} value={form.insuredPhone} onChange={set('insuredPhone')} /></div>
          <div><label style={labelStyle}>DATE OF BIRTH</label><input style={inputStyle} value={form.insuredDob} onChange={set('insuredDob')} type="date" /></div>
        </div>
        <div style={{ ...row, marginTop: '0.75rem' }}>
          <div><label style={labelStyle}>PASSPORT NUMBER</label><input style={inputStyle} value={form.passportNumber} onChange={set('passportNumber')} /></div>
          <div><label style={labelStyle}>NATIONAL ID</label><input style={inputStyle} value={form.nationalId} onChange={set('nationalId')} /></div>
        </div>
        <div style={{ ...row, marginTop: '0.75rem' }}>
          <div><label style={labelStyle}>ADDRESS</label><input style={inputStyle} value={form.insuredAddress} onChange={set('insuredAddress')} /></div>
          <div><label style={labelStyle}>COUNTRY OF ORIGIN</label><input style={inputStyle} value={form.countryOfOrigin} onChange={set('countryOfOrigin')} /></div>
        </div>

        {/* Travel Info */}
        <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'var(--gold)', letterSpacing: '0.1em', marginTop: '1.5rem', marginBottom: '0.75rem' }}>TRAVEL DETAILS</div>
        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr 1fr', gap: '1rem' }}>
          <div><label style={labelStyle}>DEPARTURE</label><input style={inputStyle} value={form.departure} onChange={set('departure')} type="date" /></div>
          <div><label style={labelStyle}>RETURN</label><input style={inputStyle} value={form.returnDate} onChange={set('returnDate')} type="date" /></div>
          <div><label style={labelStyle}>PASSENGERS</label><input style={inputStyle} value={form.passengers} onChange={(e) => setForm(prev => ({ ...prev, passengers: e.target.value }))} type="number" min="1" /></div>
        </div>

        {/* Premium note */}
        <div style={{ marginTop: '1rem', padding: '0.65rem 0.85rem', background: 'rgba(245,158,11,0.08)', borderRadius: '8px', fontSize: '0.75rem', color: '#fbbf24' }}>
          ⚡ Changing dates or passengers will recalculate the premium automatically.
        </div>

        {/* Feedback */}
        {status === 'error' && <div style={{ marginTop: '0.75rem', color: '#f87171', fontSize: '0.8rem', fontWeight: 600 }}>{errMsg}</div>}
        {status === 'success' && <div style={{ marginTop: '0.75rem', color: '#22c55e', fontSize: '0.8rem', fontWeight: 600 }}>Policy updated successfully!</div>}

        {/* Actions */}
        <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end', marginTop: '1.5rem' }}>
          <button onClick={onClose} style={{
            background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: '8px', padding: '0.55rem 1.2rem', color: 'white', cursor: 'pointer', fontSize: '0.78rem', fontWeight: 600,
          }}>Cancel</button>
          <button onClick={handleSave} disabled={status === 'saving'} style={{
            background: 'linear-gradient(135deg, var(--gold, #d4af37), #b8941f)',
            border: 'none', borderRadius: '8px', padding: '0.55rem 1.5rem',
            color: '#0f172a', cursor: 'pointer', fontSize: '0.78rem', fontWeight: 800,
            opacity: status === 'saving' ? 0.6 : 1,
          }}>
            {status === 'saving' ? 'Saving…' : 'Save Changes'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PolicyEditModal;
