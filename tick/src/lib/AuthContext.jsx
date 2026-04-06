import React, { createContext, useContext, useState, useEffect } from 'react';
import { client } from './graphql';

const AuthContext = createContext();

const LOGIN_MUTATION = `
  mutation Login($username: String!, $password: String!) {
    maljaniLogin(input: { username: $username, password: $password }) {
      authToken
      userName
      userPhone
      userRole
      userEmail
    }
  }
`;

const REGISTER_MUTATION = `
  mutation Register($fullName: String!, $email: String!, $password: String!, $accountType: String!, $phone: String, $agencyName: String) {
    maljaniRegister(input: { 
      fullName: $fullName, 
      email: $email, 
      password: $password, 
      accountType: $accountType,
      phone: $phone,
      agencyName: $agencyName
    }) {
      authToken
      userName
      userRole
      userEmail
    }
  }
`;

const UPDATE_PROFILE_MUTATION = `
  mutation UpdateProfile($name: String, $email: String, $phone: String) {
    maljaniUpdateProfile(input: { name: $name, email: $email, phone: $phone }) {
      success
      userName
      userEmail
      userPhone
    }
  }
`;

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const saved = localStorage.getItem('maljani_auth');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        // Decode JWT payload and check expiry without a library
        if (parsed.token) {
          try {
            const parts = parsed.token.split('.');
            if (parts.length === 3) {
              const payload = JSON.parse(atob(parts[1].replace(/-/g,'+').replace(/_/g,'/')));
              if (payload.exp && payload.exp < Math.floor(Date.now() / 1000)) {
                localStorage.removeItem('maljani_auth');
                setLoading(false);
                return;
              }
            }
          } catch (jwtErr) {
            console.error("AuthContext: JWT decode failed", jwtErr);
            // Don't necessarily logout yet, let the server decide if token is valid
          }
        }
        setUser(parsed);
      } catch (e) {
        localStorage.removeItem('maljani_auth');
      }
    }
    setLoading(false);
  }, []);

  const login = async (username, password) => {
    setError(null);
    setLoading(true);
    try {
      const result = await client.mutation(LOGIN_MUTATION, { username, password }).toPromise();
      if (result.error) throw new Error(result.error.message);
      
      const authData = {
        name:  result.data.maljaniLogin.userName,
email: result.data.maljaniLogin.user?.email || result.data.maljaniLogin.userEmail || '',
        phone: result.data.maljaniLogin.userPhone || '',
        role:  result.data.maljaniLogin.userRole?.toLowerCase() || 'insured',
        token: result.data.maljaniLogin.authToken
      };
      
      setUser(authData);
      localStorage.setItem('maljani_auth', JSON.stringify(authData));
      setLoading(false);
      return { success: true, role: authData.role };
    } catch (err) {
      const raw = String(err.message || '').trim();
      const authProblem = /invalid username|incorrect password|incorrect|authentication failed|user not found|unknown email address|email address.*unknown|user does not exist/i.test(raw);
      const normalized = authProblem
        ? 'Wrong username or password. Please try again or reset your password.'
        : raw || 'An error occurred during login. Please try again.';

      setError(normalized);
      setLoading(false);
      return { success: false, error: normalized };
    }
  };

  const register = async (userData) => {
    setError(null);
    setLoading(true);
    try {
      const result = await client.mutation(REGISTER_MUTATION, userData).toPromise();
      if (result.error) throw new Error(result.error.message);

      const authToken = result.data?.maljaniRegister?.authToken;
      const userName   = result.data?.maljaniRegister?.userName;
      const userRole   = result.data?.maljaniRegister?.userRole;

      if (authToken && userName) {
        const authData = {
          name:  userName,
          email: result.data?.maljaniRegister?.user?.email || result.data?.maljaniRegister?.userEmail || userData.email,
          phone: userData.phone || '',
          role:  userRole?.toLowerCase() || 'insured',
          token: authToken,
        };
        setUser(authData);
        localStorage.setItem('maljani_auth', JSON.stringify(authData));
        setLoading(false);
        return { success: true, loggedIn: true, role: authData.role };
      }

      setLoading(false);
      return { success: true, loggedIn: false };
    } catch (err) {
      setError(err.message);
      setLoading(false);
      return { success: false, error: err.message };
    }
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem('maljani_auth');
  };

  /**
   * Auto-register an insured account after a guest policy purchase.
   * Generates a temporary password — the user should set their own via email.
   */
  const autoRegisterAndLogin = async (name, email, phone) => {
    const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    const tempPassword = Array.from({ length: 12 }, () =>
      chars[Math.floor(Math.random() * chars.length)]
    ).join('') + 'Aa1!';
    try {
      const result = await client.mutation(REGISTER_MUTATION, {
        fullName: name || email,
        email,
        phone: phone || '',
        password: tempPassword,
        accountType: 'insured',
      }).toPromise();
      if (result.error || !result.data?.maljaniRegister?.authToken) {
        return { success: false };
      }
      const authData = {
        name:  result.data.maljaniRegister.userName || name || email,
        email,
        phone: phone || '',
        role:  result.data.maljaniRegister.userRole || 'insured',
        token: result.data.maljaniRegister.authToken,
      };
      setUser(authData);
      localStorage.setItem('maljani_auth', JSON.stringify(authData));
      return { success: true };
    } catch {
      return { success: false };
    }
  };

  const updateProfile = async ({ name, email, phone }) => {
    setError(null);
    try {
      const result = await client.mutation(UPDATE_PROFILE_MUTATION, { name, email, phone }).toPromise();
      if (result.error) throw new Error(result.error.message);
      const d = result.data.maljaniUpdateProfile;
      if (!d.success) throw new Error('Profile update failed');
      const updated = {
        ...user,
        name:  d.userName  || user.name,
        email: d.userEmail || user.email,
        phone: d.userPhone ?? user.phone,
      };
      setUser(updated);
      localStorage.setItem('maljani_auth', JSON.stringify(updated));
      return { success: true };
    } catch (err) {
      setError(err.message);
      return { success: false, error: err.message };
    }
  };

  const value = {
    user,
    role: user?.role || 'guest',
    login,
    register,
    logout,
    updateProfile,
    autoRegisterAndLogin,
    loading,
    error
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
