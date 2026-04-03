import React, { useState } from 'react';
import { useAuth } from '../lib/AuthContext';

const Register = ({ onNavigate }) => {
  const { register, login, error, loading } = useAuth();
  const [accountType, setAccountType] = useState('insured'); // 'insured' | 'agent'
  const [formData, setFormData] = useState({
    fullName: '',
    email: '',
    password: '',
    confirmPassword: '',
    phone: '',
    agencyName: '',
  });
  const [localError, setLocalError] = useState('');
  const [success, setSuccess] = useState(false);

  const handleChange = (e) =>
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLocalError('');

    if (!formData.fullName || !formData.email || !formData.password || !formData.phone) {
      setLocalError('Please fill in all required fields.');
      return;
    }
    if (formData.password.length < 8) {
      setLocalError('Password must be at least 8 characters.');
      return;
    }
    if (formData.password !== formData.confirmPassword) {
      setLocalError('Passwords do not match.');
      return;
    }
    if (accountType === 'agent' && !formData.agencyName.trim()) {
      setLocalError('Please enter your agency name.');
      return;
    }

    const result = await register({ ...formData, accountType });
    if (!result.success) return;

    if (result.loggedIn) {
      onNavigate('dashboard');
      return;
    }

    // Fallback: try a separate login if the token wasn't returned
    const loginResult = await login(formData.email, formData.password);
    if (loginResult.success) {
      onNavigate('dashboard');
    } else {
      setSuccess(true);
    }
  };

  if (success) {
    return (
      <div className="fade-in" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', padding: '120px 16px 60px' }}>
        <div style={{ textAlign: 'center', maxWidth: 420 }}>
          <div style={{ fontSize: 52, marginBottom: 20 }}>✅</div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 26, fontWeight: 800, marginBottom: 12 }}>Account Created!</h2>
          <p style={{ color: 'var(--slate)', marginBottom: 28 }}>Your account is ready. Please log in to continue.</p>
          <button className="btn btn--primary btn--lg" onClick={() => onNavigate('login')}>Sign In Now →</button>
        </div>
      </div>
    );
  }

  const field = (name, label, type = 'text', placeholder = '') => (
    <div>
      <label style={{ display: 'block', fontSize: 11, fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--gold)', marginBottom: 7 }}>{label}</label>
      <input
        name={name}
        type={type}
        className="form-input"
        placeholder={placeholder}
        value={formData[name]}
        onChange={handleChange}
        autoComplete={type === 'password' ? 'new-password' : 'off'}
      />
    </div>
  );

  return (
    <div className="fade-in" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', padding: '120px 16px 60px' }}>
      <div style={{ width: '100%', maxWidth: 560, background: 'var(--glass-bg-md)', border: '1px solid var(--glass-border-bright)', borderRadius: 'var(--radius-xl)', padding: '40px 44px', backdropFilter: 'blur(24px)' }}>

        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 28, fontWeight: 800, marginBottom: 8 }}>
            Create Your Account
          </h2>
          <p style={{ color: 'var(--slate)', fontSize: 14 }}>Travel protection for individuals. Policy issuance for agencies.</p>
        </div>

        {/* Account type toggle */}
        <div style={{ display: 'flex', background: 'rgba(0,0,0,0.3)', borderRadius: 'var(--radius-md)', padding: 4, marginBottom: 28, gap: 4 }}>
          {[
            { value: 'insured', label: '🧳 Individual Traveller' },
            { value: 'agent', label: '🏢 Insurance Agency' },
          ].map((t) => (
            <button
              key={t.value}
              type="button"
              onClick={() => setAccountType(t.value)}
              style={{
                flex: 1, padding: '11px 12px', borderRadius: 10, border: 'none', cursor: 'pointer',
                fontSize: 13, fontWeight: 700, fontFamily: 'var(--font-body)', transition: 'all 0.2s',
                background: accountType === t.value ? 'var(--indigo)' : 'transparent',
                color: accountType === t.value ? '#fff' : 'var(--slate)',
                boxShadow: accountType === t.value ? '0 2px 12px rgba(49,99,49,0.35)' : 'none',
              }}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Error */}
        {(error || localError) && (
          <div style={{ background: 'rgba(139,0,0,0.2)', border: '1px solid var(--crimson)', color: '#fca5a5', padding: '12px 16px', borderRadius: 8, marginBottom: 20, fontSize: 13 }}>
            {localError || error}
          </div>
        )}

        <form onSubmit={handleSubmit} noValidate>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
              {field('fullName', 'Full Name', 'text', 'Jane Doe')}
              {field('phone', 'Phone Number', 'tel', '+254 700 000 000')}
            </div>

            {field('email', 'Email Address', 'email', 'jane@example.com')}

            {accountType === 'agent' && field('agencyName', 'Agency Name', 'text', 'Safara Travels Ltd')}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
              {field('password', 'Password', 'password', '8+ characters')}
              {field('confirmPassword', 'Confirm Password', 'password', 'Repeat password')}
            </div>
          </div>

          {accountType === 'agent' && (
            <div style={{ marginTop: 18, padding: '12px 16px', background: 'rgba(49,99,49,0.1)', border: '1px solid rgba(49,99,49,0.3)', borderRadius: 8, fontSize: 12, color: '#86efac', lineHeight: 1.7 }}>
              📋 Agency accounts are reviewed within 1–2 business days. You'll receive an email once approved and can start issuing policies immediately.
            </div>
          )}

          <button
            type="submit"
            className="btn btn--primary"
            style={{ width: '100%', justifyContent: 'center', marginTop: 26, padding: '14px' }}
            disabled={loading}
          >
            {loading ? 'Creating Account…' : accountType === 'agent' ? 'Apply for Agency Account' : 'Create My Account →'}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: 20, fontSize: 13, color: 'var(--slate)' }}>
          Already have an account?{' '}
          <span style={{ color: 'var(--gold)', cursor: 'pointer', fontWeight: 700 }} onClick={() => onNavigate('login')}>
            Sign In
          </span>
        </p>
      </div>
    </div>
  );
};

export default Register;