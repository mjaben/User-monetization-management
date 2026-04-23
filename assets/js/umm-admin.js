/* UMM Admin – Dashboard JS */
(function ($) {
    'use strict';

    if (typeof UMM_Admin === 'undefined') return;

    var cfg = UMM_Admin;

    /* ── Chart.js defaults ──────────────────────────────────────── */
    Chart.defaults.color          = '#94a3b8';
    Chart.defaults.font.family    = "'Inter', -apple-system, sans-serif";
    Chart.defaults.font.size      = 12;
    Chart.defaults.plugins.legend.display = false;

    /* ── Donut chart (method breakdown) ─────────────────────────── */
    var $donut = document.getElementById('umm-chart-donut');
    if ($donut) {
        new Chart($donut, {
            type: 'doughnut',
            data: {
                labels: [cfg.i18n.airtime, cfg.i18n.bank, cfg.i18n.data],
                datasets: [{
                    data: [cfg.airtimeCount, cfg.bankCount, cfg.dataCount],
                    backgroundColor: ['#6366f1', '#06b6d4', '#10b981'],
                    borderColor:     ['#1e293b', '#1e293b', '#1e293b'],
                    borderWidth: 3,
                    hoverOffset: 6,
                }],
            },
            options: {
                cutout: '72%',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' requests';
                            },
                        },
                        backgroundColor: '#1e293b',
                        borderColor:     '#334155',
                        borderWidth:     1,
                        titleColor:      '#f1f5f9',
                        bodyColor:       '#94a3b8',
                        padding:         10,
                        cornerRadius:    8,
                    },
                },
            },
        });
    }

    /* ── Bar chart (monthly totals) ─────────────────────────────── */
    var $bar = document.getElementById('umm-chart-bar');
    if ($bar) {
        new Chart($bar, {
            type: 'bar',
            data: {
                labels: cfg.chartLabels,
                datasets: [{
                    label: 'Points approved',
                    data: cfg.chartValues,
                    backgroundColor: 'rgba(99,102,241,.35)',
                    borderColor:     '#6366f1',
                    borderWidth:     2,
                    borderRadius:    6,
                    borderSkipped:   false,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        borderColor:     '#334155',
                        borderWidth:     1,
                        titleColor:      '#f1f5f9',
                        bodyColor:       '#94a3b8',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + Number(ctx.parsed.y).toLocaleString() + ' pts';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(51,65,85,.5)', drawTicks: false },
                        border: { color: 'rgba(51,65,85,.5)' },
                        ticks: { color: '#64748b', maxRotation: 0 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(51,65,85,.5)', drawTicks: false },
                        border: { color: 'transparent' },
                        ticks: { color: '#64748b', precision: 0 },
                    },
                },
            },
        });
    }

    /* ── Filter pills ───────────────────────────────────────────── */
    var $table = $('#umm-requests-table');
    var $rows  = $table.find('tbody tr');

    // Compute counts per status
    var counts = { all: $rows.length, pending: 0, approved: 0, rejected: 0 };
    $rows.each(function () {
        var s = $(this).data('status');
        if (counts[s] !== undefined) counts[s]++;
    });

    // Set pill count badges
    $('.umm-pill').each(function () {
        var key = $(this).data('filter');
        var $c  = $(this).find('.umm-pill-count');
        if ($c.length && counts[key] !== undefined) {
            $c.text(counts[key]).show();
        }
    });

    // Filter on click
    $('.umm-pill').on('click', function () {
        var filter = $(this).data('filter');
        $('.umm-pill').removeClass('is-active');
        $(this).addClass('is-active');

        if (filter === 'all') {
            $rows.show();
        } else {
            $rows.hide().filter('[data-status="' + filter + '"]').show();
        }
    });

    /* ── Clear declined (AJAX) ──────────────────────────────────── */
    $('#umm-clear-declined').on('click', function () {
        if (!window.confirm(cfg.i18n.confirmClear)) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('…');

        $.post(cfg.ajaxurl, {
            action: 'umm_clear_declined',
            nonce:  cfg.clear_nonce,
        }, function (resp) {
            if (resp.success) {
                // Remove rejected rows from the table
                $rows.filter('[data-status="rejected"]').fadeOut(300, function () {
                    $(this).remove();

                    // Hide the clear button itself
                    $btn.closest('.umm-btn-danger').fadeOut();

                    // Reset to "All" filter
                    $('.umm-pill[data-filter="all"]').trigger('click');
                });
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : cfg.i18n.error);
                $btn.prop('disabled', false).text('🗑 Clear Declined History');
            }
        }).fail(function () {
            alert(cfg.i18n.error);
            $btn.prop('disabled', false).text('🗑 Clear Declined History');
        });
    });

}(jQuery));
