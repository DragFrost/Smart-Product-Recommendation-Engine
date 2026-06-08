import React, { useState, useEffect } from 'react';
import { Check, Copy } from 'lucide-react';
import MetricCards from './components/MetricCards';
import PerformanceChart from './components/PerformanceChart';
import RuleBuilder from './components/RuleBuilder';
import ABTestConfig from './components/ABTestConfig';

const DEFAULT_SETTINGS = {
  replace_default_related: true,
  show_fbt_in_summary: true,
  require_cookie_consent: false,
  related_limit: 4,
  fbt_limit: 3,
  show_badges: true,
  show_excerpt: false,
  show_rating: true,
  show_price: true,
  show_add_to_cart: true,
  add_to_cart_text: '',
  primary_color: '#764ba2',
  text_color: '#1e293b',
  price_color: '#764ba2',
  badge_bg_color: '#764ba2',
  btn_bg_color: '#764ba2',
  btn_text_color: '#ffffff',
  layout_mode: 'grid',
  custom_css: ''
};

const config = window.spre_admin_params || {
  api_url: '/wp-json/spre/v1',
  nonce: '',
  algorithms: [],
  settings: DEFAULT_SETTINGS
};

export default function App() {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [startDate, setStartDate] = useState(() => {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
  });
  const [endDate, setEndDate] = useState(() => {
    return new Date().toISOString().split('T')[0];
  });

  const [stats, setStats] = useState({
    summary: { impressions: 0, clicks: 0, conversions: 0, revenue: 0, ctr: 0, conv_rate: 0 },
    chart_data: [],
    top_products: []
  });
  const [settings, setSettings] = useState(config.settings);
  const [loading, setLoading] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [copiedText, setCopiedText] = useState('');

  const handleCopy = (text) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text)
        .then(() => {
          setCopiedText(text);
          setTimeout(() => setCopiedText(''), 2000);
        })
        .catch(err => {
          fallbackCopyText(text);
        });
    } else {
      fallbackCopyText(text);
    }
  };

  const fallbackCopyText = (text) => {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      const successful = document.execCommand('copy');
      if (successful) {
        setCopiedText(text);
        setTimeout(() => setCopiedText(''), 2000);
      } else {
        console.error('Fallback: Copying text command was unsuccessful');
      }
    } catch (err) {
      console.error('Fallback: Oops, unable to copy', err);
    }
    document.body.removeChild(textArea);
  };

  useEffect(() => {
    if (activeTab === 'dashboard') {
      fetchAnalytics();
    }
  }, [startDate, endDate, activeTab]);

  const fetchAnalytics = () => {
    setLoading(true);
    fetch(`${config.api_url}/admin/analytics?start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'X-WP-Nonce': config.nonce
      }
    })
      .then(res => res.json())
      .then(data => {
        if (data && data.summary) {
          setStats(data);
        }
        setLoading(false);
      })
      .catch(err => {
        console.error('Error fetching analytics:', err);
        setLoading(false);
      });
  };

  const handleSaveSettings = (e) => {
    e.preventDefault();
    setSaveSuccess(false);

    fetch(`${config.api_url}/admin/settings`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify(settings)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setSaveSuccess(true);
          setTimeout(() => setSaveSuccess(false), 3000);
        }
      })
      .catch(err => console.error('Error saving settings:', err));
  };

  const handleResetSettings = () => {
    if (window.confirm('Are you sure you want to reset all configurations and colors to defaults?')) {
      setSettings(DEFAULT_SETTINGS);
      setSaveSuccess(false);

      fetch(`${config.api_url}/admin/settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify(DEFAULT_SETTINGS)
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
          }
        })
        .catch(err => console.error('Error resetting settings:', err));
    }
  };

  return (
    <div className="spre-admin-wrap">
      <div className="spre-dashboard-header">
        <h1>Smart Product Recommendation Engine</h1>
        
        {activeTab === 'dashboard' && (
          <div className="spre-date-picker">
            <input 
              type="date" 
              value={startDate} 
              onChange={e => setStartDate(e.target.value)} 
            />
            <span>to</span>
            <input 
              type="date" 
              value={endDate} 
              onChange={e => setEndDate(e.target.value)} 
            />
          </div>
        )}
      </div>

      <nav className="spre-tabs-bar">
        <button 
          className={`spre-tab-button ${activeTab === 'dashboard' ? 'active' : ''}`}
          onClick={() => setActiveTab('dashboard')}
        >
          Analytics Dashboard
        </button>
        <button 
          className={`spre-tab-button ${activeTab === 'rules' ? 'active' : ''}`}
          onClick={() => setActiveTab('rules')}
        >
          Visual Rule Builder
        </button>
        <button 
          className={`spre-tab-button ${activeTab === 'ab_testing' ? 'active' : ''}`}
          onClick={() => setActiveTab('ab_testing')}
        >
          A/B Testing Framework
        </button>
        <button 
          className={`spre-tab-button ${activeTab === 'settings' ? 'active' : ''}`}
          onClick={() => setActiveTab('settings')}
        >
          Settings
        </button>
        <button 
          className={`spre-tab-button ${activeTab === 'shortcodes' ? 'active' : ''}`}
          onClick={() => setActiveTab('shortcodes')}
        >
          Shortcodes Guide
        </button>
      </nav>

      {loading && activeTab === 'dashboard' ? (
        <div style={{ padding: '40px', textAlign: 'center', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
          Loading statistics...
        </div>
      ) : (
        <div className="spre-tab-content">
          {activeTab === 'dashboard' && (
            <div>
              <MetricCards summary={stats.summary} />
              
              <div className="spre-layout-split">
                <div className="spre-card" style={{ marginBottom: 0 }}>
                  <h2 className="spre-card-title">Recommendation Performance Chart</h2>
                  <PerformanceChart chartData={stats.chart_data} />
                </div>
                
                <div className="spre-card" style={{ marginBottom: 0 }}>
                  <h2 className="spre-card-title">Top Recommended Products</h2>
                  <table className="spre-table">
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>Clicks</th>
                        <th>Revenue</th>
                      </tr>
                    </thead>
                    <tbody>
                      {stats.top_products.length > 0 ? (
                        stats.top_products.map(p => (
                          <tr key={p.id}>
                            <td style={{ fontWeight: '500' }}>{p.name}</td>
                            <td>{p.clicks}</td>
                            <td style={{ fontWeight: '600', color: '#10b981' }}>
                              ${parseFloat(p.revenue).toFixed(2)}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan="3" style={{ textAlign: 'center', color: '#64748b', padding: '24px 0' }}>
                            No conversion logs in this range.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'rules' && <RuleBuilder config={config} />}

          {activeTab === 'ab_testing' && <ABTestConfig config={config} />}

          {activeTab === 'settings' && (
            <div className="spre-card" style={{ maxWidth: '600px' }}>
              <h2 className="spre-card-title">General Engine Configurations</h2>
              <form onSubmit={handleSaveSettings}>
                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.replace_default_related} 
                      onChange={e => setSettings({...settings, replace_default_related: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Replace Default WooCommerce Related Products Layout
                  </label>
                </div>
                
                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_fbt_in_summary} 
                      onChange={e => setSettings({...settings, show_fbt_in_summary: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Inject Frequently Bought Together widget under Single Product details
                  </label>
                </div>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.require_cookie_consent} 
                      onChange={e => setSettings({...settings, require_cookie_consent: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Respect GDPR Cookie Consent Plugins (Complianz, CookieYes, etc.)
                  </label>
                </div>

                <div className="spre-form-group">
                  <label>Default Related Similarity Products Count</label>
                  <input 
                    type="number" 
                    min="1" 
                    max="10" 
                    value={settings.related_limit}
                    onChange={e => setSettings({...settings, related_limit: parseInt(e.target.value, 10) || 4})}
                  />
                </div>

                <div className="spre-form-group">
                  <label>Default Frequently Bought Together Products Count</label>
                  <input 
                    type="number" 
                    min="1" 
                    max="10" 
                    value={settings.fbt_limit}
                    onChange={e => setSettings({...settings, fbt_limit: parseInt(e.target.value, 10) || 3})}
                  />
                </div>

                <h3 style={{ fontSize: '15px', fontWeight: '600', marginTop: '24px', marginBottom: '12px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px', color: '#0f172a' }}>
                  Frontend Cards Layout Customization
                </h3>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_badges !== false}
                      onChange={e => setSettings({...settings, show_badges: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Show Algorithm Badges Overlay (e.g., "Smart Match", "Trending", "FBT Deal")
                  </label>
                </div>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_rating !== false}
                      onChange={e => setSettings({...settings, show_rating: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Show Product Review Rating (if available)
                  </label>
                </div>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_excerpt === true}
                      onChange={e => setSettings({...settings, show_excerpt: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Show Product Short Description / Excerpt
                  </label>
                </div>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_price !== false}
                      onChange={e => setSettings({...settings, show_price: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Show Product Price (Regular and Sale Price)
                  </label>
                </div>

                <div className="spre-form-group">
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                    <input 
                      type="checkbox" 
                      checked={settings.show_add_to_cart !== false}
                      onChange={e => setSettings({...settings, show_add_to_cart: e.target.checked})}
                      style={{ width: 'auto' }}
                    />
                    Show "Add to Cart" Button
                  </label>
                </div>

                <div className="spre-form-group" style={{ display: settings.show_add_to_cart !== false ? 'block' : 'none' }}>
                  <label>Custom "Add to Cart" Button Text (Leave empty for WooCommerce default)</label>
                  <input 
                    type="text" 
                    value={settings.add_to_cart_text || ''}
                    placeholder="e.g. Buy Now"
                    onChange={e => setSettings({...settings, add_to_cart_text: e.target.value})}
                  />
                </div>

                <div className="spre-form-group">
                  <label>Frontend Recommendations Layout Style</label>
                  <select 
                    value={settings.layout_mode || 'grid'}
                    onChange={e => setSettings({...settings, layout_mode: e.target.value})}
                  >
                    <option value="grid">Grid Layout (Standard rows)</option>
                    <option value="slider">Slider Layout (Horizontal scrolling carousel)</option>
                  </select>
                </div>

                <h3 style={{ fontSize: '15px', fontWeight: '600', marginTop: '24px', marginBottom: '12px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px', color: '#0f172a' }}>
                  Custom Color Styling
                </h3>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '16px', marginBottom: '20px' }}>
                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Primary Brand Color</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.primary_color || '#764ba2'} 
                        onChange={e => setSettings({...settings, primary_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.primary_color || ''} 
                        placeholder="#764ba2"
                        onChange={e => setSettings({...settings, primary_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>

                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Text Color</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.text_color || '#1e293b'} 
                        onChange={e => setSettings({...settings, text_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.text_color || ''} 
                        placeholder="#1e293b"
                        onChange={e => setSettings({...settings, text_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>

                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Price Tag Color</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.price_color || '#764ba2'} 
                        onChange={e => setSettings({...settings, price_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.price_color || ''} 
                        placeholder="#764ba2"
                        onChange={e => setSettings({...settings, price_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>

                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Badge Background Color</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.badge_bg_color || '#764ba2'} 
                        onChange={e => setSettings({...settings, badge_bg_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.badge_bg_color || ''} 
                        placeholder="#764ba2"
                        onChange={e => setSettings({...settings, badge_bg_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>

                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Button Background</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.btn_bg_color || '#764ba2'} 
                        onChange={e => setSettings({...settings, btn_bg_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.btn_bg_color || ''} 
                        placeholder="#764ba2"
                        onChange={e => setSettings({...settings, btn_bg_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>

                  <div className="spre-form-group" style={{ marginBottom: 0 }}>
                    <label>Button Text Color</label>
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <input 
                        type="color" 
                        value={settings.btn_text_color || '#ffffff'} 
                        onChange={e => setSettings({...settings, btn_text_color: e.target.value})}
                        style={{ width: '40px', padding: 0, height: '40px', cursor: 'pointer', border: '1px solid var(--border-color)', borderRadius: '4px' }}
                      />
                      <input 
                        type="text" 
                        value={settings.btn_text_color || ''} 
                        placeholder="#ffffff"
                        onChange={e => setSettings({...settings, btn_text_color: e.target.value})}
                        style={{ flexGrow: 1 }}
                      />
                    </div>
                  </div>
                </div>

                <h3 style={{ fontSize: '15px', fontWeight: '600', marginTop: '24px', marginBottom: '12px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px', color: '#0f172a' }}>
                  Custom CSS
                </h3>
                
                <div className="spre-form-group">
                  <label>Custom Stylesheets Injection (Will load on frontend pages)</label>
                  <textarea 
                    rows="6" 
                    value={settings.custom_css || ''} 
                    placeholder={".spre-product-card { border-radius: 8px; }\n.spre-widget-title { text-align: center; }"}
                    onChange={e => setSettings({...settings, custom_css: e.target.value})}
                    style={{ width: '100%', padding: '10px 14px', border: '1px solid var(--border-color)', borderRadius: '6px', fontFamily: 'monospace', fontSize: '13px' }}
                  />
                </div>

                <div style={{ display: 'flex', gap: '12px', alignItems: 'center', marginTop: '24px' }}>
                  <button type="submit" className="spre-btn">
                    Save Configurations
                  </button>
                  <button 
                    type="button" 
                    className="spre-btn secondary"
                    onClick={handleResetSettings}
                  >
                    Reset to Defaults
                  </button>

                  {saveSuccess && (
                    <span style={{ color: '#10b981', fontWeight: '500' }}>
                      Settings updated successfully!
                    </span>
                  )}
                </div>
              </form>
            </div>
          )}

          {activeTab === 'shortcodes' && (
            <div className="spre-card" style={{ maxWidth: '800px' }}>
              <h2 className="spre-card-title">Shortcodes Documentation</h2>
              <p style={{ color: '#64748b', marginBottom: '24px', fontSize: '14px' }}>
                Copy and paste these shortcodes into any page, post, or widget area.
              </p>
              
              <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                
                {/* Shortcode 1 */}
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                  <h3 style={{ margin: '0 0 8px 0', fontSize: '15px', color: '#639', fontWeight: '600' }}>
                    1. Personalized Recommendations
                  </h3>
                  <p style={{ fontSize: '13px', margin: '0 0 12px 0', color: '#475569' }}>
                    Displays products tailored specifically to the customer based on their category views and purchase history. Falls back to trending products if the customer has no history.
                  </p>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <code style={{ background: '#fff', border: '1px solid #cbd5e1', padding: '10px 14px', borderRadius: '6px', fontFamily: 'Courier New, Courier, monospace', flexGrow: '1', fontSize: '13px', color: '#0f172a', fontWeight: '500' }}>
                      [spre_recommendations limit="4" title="Recommended for You"]
                    </code>
                    <button 
                      type="button" 
                      className={`spre-btn-copy ${copiedText === '[spre_recommendations limit="4" title="Recommended for You"]' ? 'copied' : ''}`}
                      onClick={() => handleCopy('[spre_recommendations limit="4" title="Recommended for You"]')}
                    >
                      {copiedText === '[spre_recommendations limit="4" title="Recommended for You"]' ? (
                        <>
                          <Check size={14} />
                          <span>Copied!</span>
                        </>
                      ) : (
                        <>
                          <Copy size={14} />
                          <span>Copy Code</span>
                        </>
                      )}
                    </button>
                  </div>
                  <div style={{ fontSize: '11px', color: '#64748b', marginTop: '8px' }}>
                    <strong>Attributes:</strong> <code>limit</code> (default: 4), <code>title</code> (default: "Recommended for You")
                  </div>
                </div>

                {/* Shortcode 2 */}
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                  <h3 style={{ margin: '0 0 8px 0', fontSize: '15px', color: '#639', fontWeight: '600' }}>
                    2. Trending Products
                  </h3>
                  <p style={{ fontSize: '13px', margin: '0 0 12px 0', color: '#475569' }}>
                    Displays popular store products calculated by recent sales, add-to-carts, and page views velocity.
                  </p>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <code style={{ background: '#fff', border: '1px solid #cbd5e1', padding: '10px 14px', borderRadius: '6px', fontFamily: 'Courier New, Courier, monospace', flexGrow: '1', fontSize: '13px', color: '#0f172a', fontWeight: '500' }}>
                      [spre_trending limit="4" period="7d" title="Trending Now"]
                    </code>
                    <button 
                      type="button" 
                      className={`spre-btn-copy ${copiedText === '[spre_trending limit="4" period="7d" title="Trending Now"]' ? 'copied' : ''}`}
                      onClick={() => handleCopy('[spre_trending limit="4" period="7d" title="Trending Now"]')}
                    >
                      {copiedText === '[spre_trending limit="4" period="7d" title="Trending Now"]' ? (
                        <>
                          <Check size={14} />
                          <span>Copied!</span>
                        </>
                      ) : (
                        <>
                          <Copy size={14} />
                          <span>Copy Code</span>
                        </>
                      )}
                    </button>
                  </div>
                  <div style={{ fontSize: '11px', color: '#64748b', marginTop: '8px' }}>
                    <strong>Attributes:</strong> <code>limit</code> (default: 4), <code>period</code> (values: <code>24h</code>, <code>7d</code>, <code>30d</code>), <code>title</code>
                  </div>
                </div>

                {/* Shortcode 3 */}
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                  <h3 style={{ margin: '0 0 8px 0', fontSize: '15px', color: '#639', fontWeight: '600' }}>
                    3. Frequently Bought Together (FBT)
                  </h3>
                  <p style={{ fontSize: '13px', margin: '0 0 12px 0', color: '#475569' }}>
                    Renders co-purchase recommendations based on order histories. <strong>Note:</strong> This shortcode is context-dependent and will only display when used on a Single Product page.
                  </p>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <code style={{ background: '#fff', border: '1px solid #cbd5e1', padding: '10px 14px', borderRadius: '6px', fontFamily: 'Courier New, Courier, monospace', flexGrow: '1', fontSize: '13px', color: '#0f172a', fontWeight: '500' }}>
                      [spre_frequently_bought limit="3" title="Frequently Bought Together"]
                    </code>
                    <button 
                      type="button" 
                      className={`spre-btn-copy ${copiedText === '[spre_frequently_bought limit="3" title="Frequently Bought Together"]' ? 'copied' : ''}`}
                      onClick={() => handleCopy('[spre_frequently_bought limit="3" title="Frequently Bought Together"]')}
                    >
                      {copiedText === '[spre_frequently_bought limit="3" title="Frequently Bought Together"]' ? (
                        <>
                          <Check size={14} />
                          <span>Copied!</span>
                        </>
                      ) : (
                        <>
                          <Copy size={14} />
                          <span>Copy Code</span>
                        </>
                      )}
                    </button>
                  </div>
                  <div style={{ fontSize: '11px', color: '#64748b', marginTop: '8px' }}>
                    <strong>Attributes:</strong> <code>limit</code> (default: 3), <code>title</code>
                  </div>
                </div>

              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
