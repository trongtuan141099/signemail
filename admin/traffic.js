(() => {
    const canvas = document.getElementById('traffic-chart');
    if (!window.Chart) {
        canvas.parentElement.hidden = true;
        document.querySelector('#activity details').open = true;
        return;
    }
    const rows = JSON.parse(document.getElementById('traffic-data').textContent);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map(([date]) => date.slice(8) + '/' + date.slice(5, 7)),
            datasets: [
                {label: 'Lượt xem trang', data: rows.map(row => row[1]), backgroundColor: '#267760'},
                {label: 'Khách duy nhất', data: rows.map(row => row[2]), backgroundColor: '#536aa9'}
            ]
        },
        options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
    });
})();
