import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

export default function PerformanceChart({ chartData }) {
  // Sort data chronologically
  const sortedData = [...chartData].sort((a, b) => new Date(a.date) - new Date(b.date));

  const labels = sortedData.map(d => {
    const dateObj = new Date(d.date);
    return dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
  });

  const data = {
    labels,
    datasets: [
      {
        fill: true,
        label: 'Impressions',
        data: sortedData.map(d => d.impressions),
        borderColor: 'rgba(99, 102, 241, 0.4)',
        backgroundColor: 'rgba(99, 102, 241, 0.05)',
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 2,
      },
      {
        fill: false,
        label: 'Clicks',
        data: sortedData.map(d => d.clicks),
        borderColor: '#639',
        backgroundColor: '#639',
        tension: 0.3,
        borderWidth: 2.5,
        pointRadius: 3,
      },
      {
        fill: false,
        label: 'Conversions',
        data: sortedData.map(d => d.conversions),
        borderColor: '#10b981',
        backgroundColor: '#10b981',
        tension: 0.3,
        borderWidth: 2.5,
        pointRadius: 3,
      }
    ]
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          boxWidth: 12,
          usePointStyle: true,
          font: {
            size: 12,
            weight: '500'
          }
        }
      },
      tooltip: {
        mode: 'index',
        intersect: false,
        padding: 12,
        cornerRadius: 8,
        backgroundColor: '#0f172a'
      }
    },
    scales: {
      x: {
        grid: {
          display: false
        },
        ticks: {
          font: {
            size: 11
          }
        }
      },
      y: {
        grid: {
          color: '#f1f5f9'
        },
        ticks: {
          font: {
            size: 11
          }
        },
        min: 0
      }
    }
  };

  return (
    <div style={{ height: '320px', width: '100%', position: 'relative' }}>
      {sortedData.length > 0 ? (
        <Line data={data} options={options} />
      ) : (
        <div style={{ display: 'flex', height: '100%', alignItems: 'center', justifyContent: 'center', color: '#64748b', fontSize: '14px' }}>
          No chart metrics available for this date range.
        </div>
      )}
    </div>
  );
}
