import React from 'react';

export default function MetricCards({ summary }) {
  const formatRevenue = (value) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value || 0);
  };

  const formatNumber = (value) => {
    return new Intl.NumberFormat('en-US').format(value || 0);
  };

  const metrics = [
    {
      title: 'Recommendation Revenue',
      value: formatRevenue(summary.revenue),
      footer: 'Directly attributed sales',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
      )
    },
    {
      title: 'Click-Through Rate (CTR)',
      value: `${(summary.ctr || 0).toFixed(2)}%`,
      footer: 'Impression-to-click ratio',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#639" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
          <polyline points="16 7 22 7 22 13"></polyline>
        </svg>
      )
    },
    {
      title: 'Conversion Rate',
      value: `${(summary.conv_rate || 0).toFixed(2)}%`,
      footer: 'Click-to-checkout ratio',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      )
    },
    {
      title: 'Total Impressions',
      value: formatNumber(summary.impressions),
      footer: 'Recommendation loads',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      )
    },
    {
      title: 'Widget Clicks',
      value: formatNumber(summary.clicks),
      footer: 'Total interactions logged',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ec4899" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M10 21v-9a4 4 0 1 1 8 0v9"></path>
          <path d="M14 4a4 4 0 0 0-4 4v4"></path>
          <path d="M4 10a4 4 0 0 0 4 4h2"></path>
          <path d="M18 14h2a4 4 0 0 0 4-4V8a4 4 0 0 0-8 0v4"></path>
        </svg>
      )
    },
    {
      title: 'Purchases (Conversions)',
      value: formatNumber(summary.conversions),
      footer: 'Attributed purchases',
      icon: (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
      )
    }
  ];

  return (
    <div className="spre-metrics-grid">
      {metrics.map((m, idx) => (
        <div className="spre-metric-card" key={idx}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
            <h3>{m.title}</h3>
            {m.icon}
          </div>
          <div className="spre-metric-value">{m.value}</div>
          <div className="spre-metric-footer">{m.footer}</div>
        </div>
      ))}
    </div>
  );
}
