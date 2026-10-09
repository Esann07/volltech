document.addEventListener('DOMContentLoaded', () => {
    const drainSummary = document.getElementById('drainSummary');
    const drainValue = document.getElementById('drainValue');
    const drainBar = document.getElementById('drainBar');
    const maxEnergy = drainSummary ? parseInt(drainSummary.dataset.max, 10) : 0;

    function updateDrainUI(total) {
        if (!drainValue || !drainBar) return;
        drainValue.textContent = total;
        const pct = Math.min(100, Math.round((total / Math.max(1, maxEnergy)) * 100));
        drainBar.style.width = pct + '%';
        drainBar.classList.toggle('bar-danger', pct >= 90);
    }

    document.querySelectorAll('.toggle-equip').forEach(btn => {
        btn.addEventListener('click', async () => {
            const card = btn.closest('.gear-card');
            const inventoryId = card.dataset.inventoryId;

            btn.disabled = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('api/toggle_equip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ inventory_id: inventoryId }),
                });
                const data = await res.json();

                if (!data.ok) {
                    alert(data.error || 'Something went wrong.');
                    btn.disabled = false;
                    return;
                }

                if (data.equipped) {
                    btn.textContent = 'Unequip';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-danger');
                } else {
                    btn.textContent = 'Equip';
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-primary');
                }

                updateDrainUI(data.total_drain);
            } catch (err) {
                alert('Network error — please try again.');
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.flash').forEach(el => {
        setTimeout(() => { el.style.opacity = '0'; }, 4000);
    });
});
