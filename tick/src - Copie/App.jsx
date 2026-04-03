import React from 'react'
import { Provider } from 'urql'
import { client } from './lib/graphql'
import { AuthProvider } from './lib/AuthContext'
import LandingPage from './components/LandingPage'
import QuoteWizard from './components/QuoteWizard'
import Header from './components/Header'
import Footer from './components/Footer'
import Login from './components/Login'
import Register from './components/Register'
import Dashboard from './components/Dashboard'
import PolicyDetail from './components/PolicyDetail'
import Catalog from './components/Catalog'
import VerifyPolicy from './components/VerifyPolicy'
import AboutPage from './components/AboutPage'
import AgenciesPage from './components/AgenciesPage'

function AppContent() {
  const [history, setHistory] = React.useState([{ view: 'landing', policyId: null, searchData: null, forceStep: null }]);
  const [historyIdx, setHistoryIdx] = React.useState(0);

  const activeView    = history[historyIdx].view;
  const selectedPolicyId = history[historyIdx].policyId;
  const canGoBack     = historyIdx > 0;
  const canGoForward  = historyIdx < history.length - 1;

  const handleNavigate = (view, policyId = null, searchData = null, forceStep = null) => {
    const entry = { view, policyId, searchData, forceStep };
    setHistory(prev => [...prev.slice(0, historyIdx + 1), entry]);
    setHistoryIdx(prev => prev + 1);
    window.scrollTo(0, 0);
  };

  const handleBack = () => {
    if (!canGoBack) return;
    setHistoryIdx(prev => prev - 1);
    window.scrollTo(0, 0);
  };

  const handleForward = () => {
    if (!canGoForward) return;
    setHistoryIdx(prev => prev + 1);
    window.scrollTo(0, 0);
  };

  const renderView = () => {
    switch(activeView) {
      case 'dashboard':
        return <Dashboard onNavigate={handleNavigate} />;
      case 'wizard':
        return (
          <div className="fade-in" style={{ padding: '4rem 0', maxWidth: '800px', margin: '0 auto' }}>
            <QuoteWizard
              initialPolicyId={history[historyIdx].policyId}
              initialSearchData={history[historyIdx].searchData}
              initialStep={history[historyIdx].forceStep}
              onNavigate={handleNavigate}
            />
            <button
              className="btn-luxury"
              style={{
                marginTop: '2rem',
                background: 'transparent',
                border: '1px solid var(--glass-border)',
                color: 'white',
                width: '100%'
              }}
              onClick={() => handleNavigate('landing')}
            >
              Back to Home
            </button>
          </div>
        );
      case 'login':
        return <Login onNavigate={handleNavigate} />;
      case 'register':
        return <Register onNavigate={handleNavigate} />;
      case 'catalog':
        return <Catalog onNavigate={handleNavigate} />;
      case 'verify':
        return <VerifyPolicy onNavigate={handleNavigate} />;
      case 'about':
        return <AboutPage onNavigate={handleNavigate} />;
      case 'agencies':
        return <AgenciesPage onNavigate={handleNavigate} />;
      case 'policy-detail':
        return (
          <PolicyDetail 
            policyId={selectedPolicyId} 
            searchData={history[historyIdx].searchData}
            onBack={() => handleNavigate('landing')} 
            onStartWizard={(id, data, step) => handleNavigate('wizard', id, data, step)}
          />
        );
      case 'landing':
      default:
        return (
          <LandingPage 
            onStartWizard={(id, data, step) => handleNavigate('wizard', id ?? null, data ?? null, step ?? null)} 
            onNavigate={handleNavigate} 
          />
        );
    }
  }

  return (
    <div className="app-container">
      <div className="bg-mesh" aria-hidden="true" />
      <div className="bg-grid" aria-hidden="true" />
      <Header
        onNavigate={handleNavigate}
        activeView={activeView}
        canGoBack={canGoBack}
        canGoForward={canGoForward}
        onBack={handleBack}
        onForward={handleForward}
      />
      <main style={{ position: 'relative', zIndex: 1 }}>
        {renderView()}
      </main>
      <Footer onNavigate={handleNavigate} />
    </div>
  )
}

function App() {
  return (
    <Provider value={client}>
      <AuthProvider>
        <AppContent />
      </AuthProvider>
    </Provider>
  )
}

export default App
