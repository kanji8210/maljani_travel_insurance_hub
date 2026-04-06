import React, { useState } from 'react';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';

const fieldStyle = { display: 'flex', flexDirection: 'column', gap: 6 };
const labelStyle = { fontSize: 11, fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--gold)' };
const inputStyle = {
  padding: '10px 14px', borderRadius: 8, border: '1px solid var(--glass-border)',
  background: 'rgba(255,255,255,0.05)', color: '#fff', fontSize: 14,
  fontFamily: 'var(--font-body)', outline: 'none', width: '100%', boxSizing: 'border-box',
};

const ProfileEditModal = ({ onClose }) => {
  const { user, updateProfile } = useAuth();
  const { mobile } = useResponsive();
  const [form, setForm] = useState({
    name:  user?.name  || '',
    email: user?.email || '',
    phone: user?.phone || '',
  });
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState(null);

  const set = (key, val) => setForm(f => ({ ...f, [key]: val }));

  const handleSave = async () => {
    setSaving(true);
    setFeedback(null);
    const res = await updateProfile(form);
    setSaving(false);
    if (res.success) {
      setFeedback({ type: 'success', msg: 'Profile updated successfully.' });
      setTimeout(() => onClose(), 1200);
    } else {
      setFeedback({ type: 'error', msg: res.error || 'Could not update profile.' });
    }
  };

  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.82)', backdropFilter: 'blur(8px)', zIndex: 2000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }} onClick={onClose}>
      <div className="glass-card fade-in" style={{ maxWidth: 440, width: '100%', padding: mobile ? '1.5rem' : '2.25rem', position: 'relative' }} onClick={e => e.stopPropagation()}>

        {/* Close */}
        <button type="button" onClick={onClose} style={{ position: 'absolute', top: 14, right: 14, background: 'var(--glass-bg)', border: '1px solid var(--glass-border)', color: 'var(--slate)', fontSize: 18, borderRadius: 8, padding: '4px 10px', cursor: 'pointer' }}>✕</button>

        {/* Avatar + title */}
        <div style={{ textAlign: 'center', marginBottom: 24 }}>
          <div style={{ width: 56, height: 56, borderRadius: '50%', background: 'linear-gradient(135deg,var(--indigo),var(--indigo-glow))', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, fontWeight: 800, margin: '0 auto 12px', color: '#fff', fontFamily: 'var(--font-display)' }}>
            {form.name?.charAt(0)?.toUpperCase() || '?'}
          </div>
          <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 800, margin: 0 }}>
            Edit <span style={{ color: 'var(--gold)' }}>Profile</span>
          </h3>
          <p style={{ color: 'var(--slate)', fontSize: 13, margin: '6px 0 0' }}>Update your account details</p>
        </div>

        {/* Fields */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={fieldStyle}>
            <label style={labelStyle}>Full Name</label>
            <input type="text" style={inputStyle} placeholder="Your legal name" value={form.name} onChange={e => set('name', e.target.value)} />
          </div>
          <div style={fieldStyle}>
            <label style={labelStyle}>Email Address</label>
            <input type="email" style={inputStyle} placeholder="you@example.com" value={form.email} onChange={e => set('email', e.target.value)} />
          </div>
          <div style={fieldStyle}>
            <label style={labelStyle}>Phone Number</label>
            <input type="tel" style={inputStyle} placeholder="+254 700 000 000" value={form.phone} onChange={e => set('phone', e.target.value)} />
          </div>
        </div>

        {/* Feedback */}
        {feedback && (
          <div style={{
            marginTop: 14, padding: '10px 14px', borderRadius: 8, fontSize: 13, fontWeight: 600,
            background: feedback.type === 'success' ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)',
            border: `1px solid ${feedback.type === 'success' ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)'}`,
            color: feedback.type === 'success' ? '#86efac' : '#fca5a5',
          }}>
            {feedback.msg}
          </div>
        )}

        {/* Buttons */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 20 }}>
          <button type="button" onClick={onClose} style={{ padding: '11px', borderRadius: 8, border: '1px solid var(--glass-border)', background: 'none', color: '#fff', cursor: 'pointer', fontSize: 13, fontWeight: 700 }}>Cancel</button>
          <button type="button" onClick={handleSave} disabled={saving || (!form.name && !form.email)}
            style={{ padding: '11px', borderRadius: 8, border: 'none', background: 'var(--indigo)', color: '#fff', cursor: saving ? 'wait' : 'pointer', fontSize: 13, fontWeight: 800, opacity: saving ? 0.7 : 1 }}>
            {saving ? 'Saving…' : 'Save Changes'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ProfileEditModal;
