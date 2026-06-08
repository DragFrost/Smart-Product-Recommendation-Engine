import React, { useState, useEffect } from 'react';

export default function ABTestConfig({ config }) {
  const [tests, setTests] = useState([]);
  const [isCreating, setIsCreating] = useState(false);

  // Form State
  const [name, setName] = useState('');
  const [algorithmA, setAlgorithmA] = useState('related');
  const [algorithmB, setAlgorithmB] = useState('fbt');
  const [trafficSplit, setTrafficSplit] = useState(50);

  useEffect(() => {
    fetchTests();
  }, []);

  const fetchTests = () => {
    fetch(`${config.api_url}/admin/ab-tests`, {
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => setTests(data || []))
      .catch(err => console.error(err));
  };

  const handleCreateTest = (e) => {
    e.preventDefault();

    const payload = {
      name,
      algorithm_a: algorithmA,
      algorithm_b: algorithmB,
      traffic_split: trafficSplit
    };

    fetch(`${config.api_url}/admin/ab-tests`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setIsCreating(false);
          setName('');
          setAlgorithmA('related');
          setAlgorithmB('fbt');
          setTrafficSplit(50);
          fetchTests();
        }
      })
      .catch(err => console.error(err));
  };

  const handleStart = (id) => {
    fetch(`${config.api_url}/admin/ab-tests/${id}/start`, {
      method: 'POST',
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          fetchTests();
        }
      })
      .catch(err => console.error(err));
  };

  const handleEnd = (id) => {
    fetch(`${config.api_url}/admin/ab-tests/${id}/end`, {
      method: 'POST',
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          fetchTests();
        }
      })
      .catch(err => console.error(err));
  };

  const handleDelete = (id) => {
    if (!confirm('Are you sure you want to delete this experiment config?')) return;

    fetch(`${config.api_url}/admin/ab-tests/${id}`, {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          fetchTests();
        }
      })
      .catch(err => console.error(err));
  };

  const getAlgoLabel = (val) => {
    const algo = config.algorithms.find(a => a.value === val);
    return algo ? algo.label : val;
  };

  return (
    <div className="spre-ab-panel">
      {isCreating ? (
        <div className="spre-card" style={{ maxWidth: '600px' }}>
          <h2 className="spre-card-title">Setup A/B Split Experiment</h2>
          <form onSubmit={handleCreateTest}>
            <div className="spre-form-group">
              <label>Experiment Name</label>
              <input 
                type="text" 
                value={name} 
                onChange={e => setName(e.target.value)} 
                placeholder="e.g. Related Similarity vs Co-purchases" 
                required 
              />
            </div>

            <div className="spre-form-group">
              <label>Algorithm A (Control Variation)</label>
              <select value={algorithmA} onChange={e => setAlgorithmA(e.target.value)}>
                {config.algorithms.map(a => (
                  <option key={a.value} value={a.value}>{a.label}</option>
                ))}
              </select>
            </div>

            <div className="spre-form-group">
              <label>Algorithm B (Challenger Variation)</label>
              <select value={algorithmB} onChange={e => setAlgorithmB(e.target.value)}>
                {config.algorithms.map(a => (
                  <option key={a.value} value={a.value}>{a.label}</option>
                ))}
              </select>
            </div>

            <div className="spre-form-group">
              <label>Traffic Split: Group A ({trafficSplit}%) / Group B ({100 - trafficSplit}%)</label>
              <input 
                type="range" 
                min="1" 
                max="99" 
                value={trafficSplit} 
                onChange={e => setTrafficSplit(parseInt(e.target.value, 10))} 
              />
              <span style={{ fontSize: '11px', color: '#64748b' }}>Percentage of users assigned to Variation A. Remaining are routed to B.</span>
            </div>

            <div style={{ display: 'flex', gap: '10px' }}>
              <button type="submit" className="spre-btn">
                Launch Experiment Draft
              </button>
              <button type="button" className="spre-btn secondary" onClick={() => setIsCreating(false)}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      ) : (
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
            <h2 style={{ fontSize: '18px', fontWeight: '600', margin: '0' }}>A/B Split Experiments</h2>
            <button className="spre-btn" onClick={() => setIsCreating(true)}>
              + Design Experiment
            </button>
          </div>

          <div className="spre-tests-list">
            {tests.length > 0 ? (
              tests.map(test => (
                <div className="spre-card" key={test.id} style={{ padding: '24px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                    <div>
                      <h3 style={{ fontSize: '16px', fontWeight: '700', margin: '0 0 4px 0' }}>{test.name}</h3>
                      <span style={{ fontSize: '11px', color: '#64748b' }}>Created: {test.created_at}</span>
                    </div>

                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                      <span className={`spre-badge ${test.status === 'active' ? 'success' : ''}`}>
                        {test.status.toUpperCase()}
                      </span>
                      {test.status === 'draft' && (
                        <button className="spre-btn" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={() => handleStart(test.id)}>
                          Activate
                        </button>
                      )}
                      {test.status === 'active' && (
                        <button className="spre-btn danger" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={() => handleEnd(test.id)}>
                          Stop Test
                        </button>
                      )}
                      <button className="spre-btn secondary" style={{ padding: '4px 10px', fontSize: '12px', borderColor: '#ef4444', color: '#ef4444' }} onClick={() => handleDelete(test.id)}>
                        Delete
                      </button>
                    </div>
                  </div>

                  <div className="spre-layout-split" style={{ gridTemplateColumns: '1fr 1fr', gap: '20px', borderTop: '1px solid #f1f5f9', paddingTop: '16px' }}>
                    {/* Variation A Details */}
                    <div style={{ background: '#f8fafc', padding: '16px', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', color: '#639', fontWeight: '600' }}>
                        Variation A: {getAlgoLabel(test.algorithm_a)} ({test.traffic_split}%)
                      </h4>
                      <table style={{ width: '100%', fontSize: '13px' }}>
                        <tbody>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>Impressions:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{test.metrics?.A?.impressions || 0}</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>CTR:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{(test.metrics?.A?.ctr || 0).toFixed(2)}%</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>Conversions:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{test.metrics?.A?.conversions || 0}</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0', fontWeight: '600' }}>Revenue:</td>
                            <td style={{ textAlign: 'right', fontWeight: '700', color: '#10b981' }}>
                              ${parseFloat(test.metrics?.A?.revenue || 0).toFixed(2)}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    {/* Variation B Details */}
                    <div style={{ background: '#f8fafc', padding: '16px', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', color: '#0369a1', fontWeight: '600' }}>
                        Variation B: {getAlgoLabel(test.algorithm_b)} ({100 - test.traffic_split}%)
                      </h4>
                      <table style={{ width: '100%', fontSize: '13px' }}>
                        <tbody>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>Impressions:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{test.metrics?.B?.impressions || 0}</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>CTR:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{(test.metrics?.B?.ctr || 0).toFixed(2)}%</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0' }}>Conversions:</td>
                            <td style={{ textAlign: 'right', fontWeight: '500' }}>{test.metrics?.B?.conversions || 0}</td>
                          </tr>
                          <tr>
                            <td style={{ color: '#64748b', padding: '4px 0', fontWeight: '600' }}>Revenue:</td>
                            <td style={{ textAlign: 'right', fontWeight: '700', color: '#10b981' }}>
                              ${parseFloat(test.metrics?.B?.revenue || 0).toFixed(2)}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div style={{ padding: '40px', textAlign: 'center', background: '#fff', borderRadius: '10px', border: '1px solid #e2e8f0', color: '#64748b' }}>
                No split tests designed yet. Set up experiments to benchmark performance.
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
