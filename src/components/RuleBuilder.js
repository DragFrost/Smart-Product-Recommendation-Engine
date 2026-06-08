import React, { useState, useEffect } from 'react';

export default function RuleBuilder({ config }) {
  const [rules, setRules] = useState([]);
  const [categories, setCategories] = useState([]);
  const [isEditing, setIsEditing] = useState(false);
  
  // Form State
  const [ruleId, setRuleId] = useState(null);
  const [name, setName] = useState('');
  const [priority, setPriority] = useState(0);
  const [conditions, setConditions] = useState([]);
  const [actions, setActions] = useState([]);
  
  // Autocomplete suggestions state
  const [productQuery, setProductQuery] = useState({});
  const [productSuggestions, setProductSuggestions] = useState({});

  useEffect(() => {
    fetchRules();
    fetchCategories();
  }, []);

  const fetchRules = () => {
    fetch(`${config.api_url}/admin/rules`, {
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => setRules(data || []))
      .catch(err => console.error(err));
  };

  const fetchCategories = () => {
    fetch(`${config.api_url}/admin/categories`, {
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => setCategories(data || []))
      .catch(err => console.error(err));
  };

  const searchProducts = (term, index, fieldType) => {
    if (!term || term.length < 2) return;
    
    fetch(`${config.api_url}/admin/products/search?q=${encodeURIComponent(term)}`, {
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => {
        const key = `${fieldType}_${index}`;
        setProductSuggestions(prev => ({ ...prev, [key]: data || [] }));
      })
      .catch(err => console.error(err));
  };

  const handleCreateNew = () => {
    setRuleId(null);
    setName('');
    setPriority(0);
    setConditions([{ type: 'viewing_category', value: '' }]);
    setActions([{ type: 'recommend_category', value: '' }]);
    setIsEditing(true);
  };

  const handleEdit = (rule) => {
    setRuleId(rule.id);
    setName(rule.rule_name);
    setPriority(parseInt(rule.priority, 10));
    setConditions(rule.conditions || []);
    setActions(rule.actions || []);
    setIsEditing(true);
  };

  const handleDelete = (id) => {
    if (!confirm('Are you sure you want to delete this rule?')) return;

    fetch(`${config.api_url}/admin/rules/${id}`, {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': config.nonce }
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          fetchRules();
        }
      })
      .catch(err => console.error(err));
  };

  const handleSave = (e) => {
    e.preventDefault();

    const payload = {
      name,
      priority,
      conditions,
      actions
    };

    const url = ruleId 
      ? `${config.api_url}/admin/rules/${ruleId}`
      : `${config.api_url}/admin/rules`;
      
    const method = ruleId ? 'PUT' : 'POST';

    fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setIsEditing(false);
          fetchRules();
        }
      })
      .catch(err => console.error(err));
  };

  const addCondition = () => {
    setConditions([...conditions, { type: 'viewing_category', value: '' }]);
  };

  const removeCondition = (idx) => {
    setConditions(conditions.filter((_, i) => i !== idx));
  };

  const updateCondition = (idx, key, val) => {
    const next = [...conditions];
    next[idx][key] = val;
    if (key === 'type') {
      next[idx].value = ''; // Reset value on type switch
    }
    setConditions(next);
  };

  const addAction = () => {
    setActions([...actions, { type: 'recommend_category', value: '' }]);
  };

  const removeAction = (idx) => {
    setActions(actions.filter((_, i) => i !== idx));
  };

  const updateAction = (idx, key, val) => {
    const next = [...actions];
    next[idx][key] = val;
    if (key === 'type') {
      next[idx].value = '';
    }
    setConditions(conditions);
    setActions(next);
  };

  const getCategoryName = (id) => {
    const cat = categories.find(c => parseInt(c.id, 10) === parseInt(id, 10));
    return cat ? cat.name : `Category ID: ${id}`;
  };

  return (
    <div className="spre-rules-panel">
      {isEditing ? (
        <div className="spre-card">
          <h2 className="spre-card-title">{ruleId ? 'Edit Recommendation Rule' : 'Create Custom Rule'}</h2>
          <form onSubmit={handleSave}>
            <div className="spre-form-group">
              <label>Rule Name</label>
              <input 
                type="text" 
                value={name} 
                onChange={e => setName(e.target.value)} 
                placeholder="e.g. Shoe Cross-sell Socks" 
                required 
              />
            </div>

            <div className="spre-form-group">
              <label>Processing Priority</label>
              <input 
                type="number" 
                value={priority} 
                onChange={e => setPriority(parseInt(e.target.value, 10) || 0)} 
                placeholder="0" 
              />
              <span style={{ fontSize: '11px', color: '#64748b' }}>Higher priority rules execute first.</span>
            </div>

            <h3 style={{ fontSize: '14px', fontWeight: '600', marginTop: '20px', marginBottom: '8px' }}>IF Conditions (AND Logic)</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '20px' }}>
              {conditions.map((cond, idx) => (
                <div key={idx} style={{ display: 'flex', gap: '10px', alignItems: 'center', background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
                  <select 
                    value={cond.type} 
                    onChange={e => updateCondition(idx, 'type', e.target.value)}
                    style={{ maxWidth: '200px' }}
                  >
                    <option value="viewing_category">Viewing Product in Category</option>
                    <option value="viewing_product">Viewing Specific Product</option>
                    <option value="cart_contains_category">Cart Contains Category</option>
                    <option value="cart_contains_product">Cart Contains Specific Product</option>
                  </select>

                  {/* Value options depending on selected type */}
                  {cond.type.includes('category') ? (
                    <select 
                      value={cond.value} 
                      onChange={e => updateCondition(idx, 'value', e.target.value)}
                      required
                    >
                      <option value="">Select Category...</option>
                      {categories.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  ) : (
                    <div style={{ position: 'relative', flexGrow: '1' }}>
                      <input 
                        type="text" 
                        placeholder="Type product name..." 
                        value={productQuery[`cond_${idx}`] || ''}
                        onChange={e => {
                          const val = e.target.value;
                          setProductQuery(prev => ({ ...prev, [`cond_${idx}`]: val }));
                          searchProducts(val, idx, 'cond');
                        }}
                      />
                      {/* Dropdown search suggestions */}
                      {productSuggestions[`cond_${idx}`] && productSuggestions[`cond_${idx}`].length > 0 && (
                        <div style={{ position: 'absolute', top: '100%', left: '0', right: '0', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '4px', zIndex: '100', boxShadow: '0 4px 6px rgba(0,0,0,0.1)', maxH: '150px', overflowY: 'auto' }}>
                          {productSuggestions[`cond_${idx}`].map(p => (
                            <div 
                              key={p.id} 
                              style={{ padding: '8px 12px', cursor: 'pointer', borderBottom: '1px solid #f1f5f9' }}
                              onClick={() => {
                                updateCondition(idx, 'value', p.id);
                                setProductQuery(prev => ({ ...prev, [`cond_${idx}`]: p.name }));
                                setProductSuggestions(prev => ({ ...prev, [`cond_${idx}`]: [] }));
                              }}
                            >
                              {p.name} {p.sku ? `(SKU: ${p.sku})` : ''}
                            </div>
                          ))}
                        </div>
                      )}
                      {cond.value && (
                        <div style={{ fontSize: '11px', color: '#10b981', marginTop: '4px' }}>
                          Selected Product ID: {cond.value}
                        </div>
                      )}
                    </div>
                  )}

                  {conditions.length > 1 && (
                    <button type="button" className="spre-btn danger" style={{ padding: '6px 12px' }} onClick={() => removeCondition(idx)}>
                      Remove
                    </button>
                  )}
                </div>
              ))}
              <button type="button" className="spre-btn secondary" style={{ alignSelf: 'flex-start' }} onClick={addCondition}>
                + Add Condition
              </button>
            </div>

            <h3 style={{ fontSize: '14px', fontWeight: '600', marginTop: '20px', marginBottom: '8px' }}>THEN Action</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '24px' }}>
              {actions.map((action, idx) => (
                <div key={idx} style={{ display: 'flex', gap: '10px', alignItems: 'center', background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
                  <select 
                    value={action.type} 
                    onChange={e => updateAction(idx, 'type', e.target.value)}
                    style={{ maxWidth: '200px' }}
                  >
                    <option value="recommend_category">Recommend Products in Category</option>
                    <option value="recommend_products">Recommend Specific Product List</option>
                  </select>

                  {action.type === 'recommend_category' ? (
                    <select 
                      value={action.value} 
                      onChange={e => updateAction(idx, 'value', e.target.value)}
                      required
                    >
                      <option value="">Select Category...</option>
                      {categories.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  ) : (
                    <div style={{ position: 'relative', flexGrow: '1' }}>
                      <input 
                        type="text" 
                        placeholder="Type product name to add..." 
                        value={productQuery[`act_${idx}`] || ''}
                        onChange={e => {
                          const val = e.target.value;
                          setProductQuery(prev => ({ ...prev, [`act_${idx}`]: val }));
                          searchProducts(val, idx, 'act');
                        }}
                      />
                      {productSuggestions[`act_${idx}`] && productSuggestions[`act_${idx}`].length > 0 && (
                        <div style={{ position: 'absolute', top: '100%', left: '0', right: '0', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '4px', zIndex: '100', boxShadow: '0 4px 6px rgba(0,0,0,0.1)' }}>
                          {productSuggestions[`act_${idx}`].map(p => (
                            <div 
                              key={p.id} 
                              style={{ padding: '8px 12px', cursor: 'pointer', borderBottom: '1px solid #f1f5f9' }}
                              onClick={() => {
                                const currentList = Array.isArray(action.value) ? action.value : [];
                                if (!currentList.includes(p.id)) {
                                  updateAction(idx, 'value', [...currentList, p.id]);
                                }
                                setProductQuery(prev => ({ ...prev, [`act_${idx}`]: '' }));
                                setProductSuggestions(prev => ({ ...prev, [`act_${idx}`]: [] }));
                              }}
                            >
                              + {p.name}
                            </div>
                          ))}
                        </div>
                      )}
                      <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginTop: '6px' }}>
                        {Array.isArray(action.value) && action.value.map(pId => (
                          <span key={pId} style={{ background: '#e2e8f0', padding: '2px 8px', borderRadius: '4px', fontSize: '11px', display: 'flex', alignItems: 'center', gap: '4px' }}>
                            ID: {pId}
                            <button 
                              type="button" 
                              style={{ border: 'none', background: 'none', cursor: 'pointer', fontWeight: 'bold' }} 
                              onClick={() => updateAction(idx, 'value', action.value.filter(id => id !== pId))}
                            >
                              x
                            </button>
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {actions.length > 1 && (
                    <button type="button" className="spre-btn danger" style={{ padding: '6px 12px' }} onClick={() => removeAction(idx)}>
                      Remove
                    </button>
                  )}
                </div>
              ))}
            </div>

            <div style={{ display: 'flex', gap: '10px' }}>
              <button type="submit" className="spre-btn">
                Save Rule Configuration
              </button>
              <button type="button" className="spre-btn secondary" onClick={() => setIsEditing(false)}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      ) : (
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
            <h2 style={{ fontSize: '18px', fontWeight: '600', margin: '0' }}>Recommendation Rules</h2>
            <button className="spre-btn" onClick={handleCreateNew}>
              + Add Custom Rule
            </button>
          </div>

          <div className="spre-rules-list">
            {rules.length > 0 ? (
              rules.map(rule => (
                <div className="spre-rule-item" key={rule.id}>
                  <div className="spre-rule-header">
                    <span>{rule.rule_name}</span>
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                      <span className="spre-badge" style={{ background: '#f1f5f9', color: '#475569' }}>
                        Priority: {rule.priority}
                      </span>
                      <span className={`spre-badge ${rule.status === 'active' ? 'success' : ''}`}>
                        {rule.status.toUpperCase()}
                      </span>
                      <button className="spre-btn secondary" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={() => handleEdit(rule)}>
                        Edit
                      </button>
                      <button className="spre-btn danger" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={() => handleDelete(rule.id)}>
                        Delete
                      </button>
                    </div>
                  </div>
                  <div className="spre-rule-details">
                    <div>
                      <strong>IF:</strong> {rule.conditions.map((c, i) => (
                        <span key={i}>
                          {i > 0 && ' AND '}
                          <span style={{ color: '#0f172a', fontWeight: '500' }}>
                            {c.type.replace(/_/g, ' ')}
                          </span> = {c.type.includes('category') ? getCategoryName(c.value) : `Product ID ${c.value}`}
                        </span>
                      ))}
                    </div>
                    <div>
                      <strong>THEN:</strong> {rule.actions.map((a, i) => (
                        <span key={i}>
                          Recommend {a.type === 'recommend_category' ? `Category: ${getCategoryName(a.value)}` : `Product List [${(a.value || []).join(', ')}]`}
                        </span>
                      ))}
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div style={{ padding: '40px', textAlign: 'center', background: '#fff', borderRadius: '10px', border: '1px solid #e2e8f0', color: '#64748b' }}>
                No custom recommendation rules set. Rules let you bypass standard recommendations with exact triggers.
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
