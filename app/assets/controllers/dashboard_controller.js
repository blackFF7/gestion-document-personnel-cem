import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

export default class extends Controller {
    static values = {
        role:             String,
        byStatus:         Object,
        byConf:           Object,
        byDirection:      Array,
        byAgence:         Array,
        monthlyDeposits:  Array,
        bySexe:           Object,
        byFamille:        Object,
        byAnciennete:     Array,
        archivesByType:   Object,
        archivesMonthly:  Array,
        approByType:      Array,
        queueWeekly:      Array,
        myDocsByStatus:   Object,
        myMonthlyDeposit: Array,
    }

    static targets = [
        'chartStatus', 'chartConf', 'chartMonthly', 'chartDir', 'chartAgence',
        'chartSexe', 'chartFamille', 'chartAnciennete',
        'chartType', 'chartMonthlyChef',
        'chartQueue', 'chartApproRate',
        'chartMyStatus', 'chartMyMonthly',
    ]

    charts = []

    connect() {
        const role = this.roleValue
        if (role === 'ADMIN') this.buildAdmin()
        if (role === 'RH')    this.buildRh()
        if (role === 'CHEF')  this.buildChef()
        if (role === 'SAP')   this.buildSap()
        if (role === 'USER')  this.buildUser()
    }

    disconnect() {
        this.charts.forEach(c => c.destroy())
        this.charts = []
    }

    // ══════════════════════════════════════════════════════════════
    // UTILITAIRES DE RÉSUMÉ
    // ══════════════════════════════════════════════════════════════

    /**
     * Garde les N plus grands + regroupe le reste en "Autres"
     * @param {string[]} labels
     * @param {number[]} data
     * @param {number}   topN   — nombre d'éléments à conserver (défaut 8)
     * @returns {{ labels, data, hasOthers, othersCount }}
     */
    topN(labels, data, topN = 8) {
        if (labels.length <= topN) {
            return { labels, data, hasOthers: false, othersCount: 0 }
        }

        // Trier par valeur décroissante
        const indexed = labels.map((l, i) => ({ l, v: data[i] }))
        indexed.sort((a, b) => b.v - a.v)

        const top    = indexed.slice(0, topN)
        const others = indexed.slice(topN)
        const othersTotal = others.reduce((s, x) => s + x.v, 0)
        const othersCount = others.length

        return {
            labels:      [...top.map(x => x.l), `Autres (${othersCount})`],
            data:        [...top.map(x => x.v),  othersTotal],
            hasOthers:   true,
            othersCount,
        }
    }

    /**
     * Pour un objet {label: valeur}, applique topN
     */
    topNObj(obj, topN = 8) {
        return this.topN(Object.keys(obj), Object.values(obj).map(Number), topN)
    }

    /**
     * Pour un tableau [{labelKey, valueKey}], applique topN
     */
    topNArr(arr, labelKey, valueKey, topN = 8) {
        return this.topN(
            arr.map(r => r[labelKey] ?? 'Non défini'),
            arr.map(r => Number(r[valueKey])),
            topN
        )
    }

    // ══════════════════════════════════════════════════════════════
    // CONSTRUCTEURS DE GRAPHES
    // ══════════════════════════════════════════════════════════════

    push(chart) { this.charts.push(chart); return chart }

    /**
     * Doughnut avec légende externe (valeur + %)
     * Résumé automatique si > maxSlices tranches
     */
    doughnut(canvas, legendEl, labels, data, colors, maxSlices = 7) {
        // Résumé si trop de tranches
        let finalLabels = labels
        let finalData   = data
        if (labels.length > maxSlices) {
            const r = this.topN(labels, data, maxSlices - 1)
            finalLabels = r.labels
            finalData   = r.data
            // Couleur grise pour "Autres"
            colors = [...colors.slice(0, maxSlices - 1), '#B4B2A9']
        }

        const total = finalData.reduce((a, b) => a + b, 0)

        if (legendEl) {
            legendEl.innerHTML = finalLabels.map((l, i) => {
                const pct = total > 0 ? Math.round(finalData[i] / total * 100) : 0
                const col = colors[i] ?? '#B4B2A9'
                return `<div class="legend-item">
                    <span class="legend-dot" style="background:${col}"></span>
                    <span class="legend-label">${l}</span>
                    <span class="legend-val">${finalData[i].toLocaleString()} <em>(${pct}%)</em></span>
                </div>`
            }).join('')
        }

        return this.push(new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: finalLabels,
                datasets: [{
                    data: finalData,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0
                                return ` ${ctx.label} : ${ctx.parsed.toLocaleString()} (${pct}%)`
                            }
                        }
                    }
                }
            }
        }))
    }

    /**
     * Barres horizontales avec résumé Top N automatique
     * Affiche le nombre d'éléments masqués dans le titre si résumé
     */
    barH(canvas, labels, data, color, topN = 10) {
        const r   = this.topN(labels, data, topN)
        const max = Math.max(...r.data)

        // Couleurs : dégradé d'opacité selon la valeur, gris pour "Autres"
        const bgColors = r.data.map((v, i) => {
            if (r.hasOthers && i === r.data.length - 1) return '#B4B2A980'
            const alpha = Math.round((0.45 + (v / max) * 0.55) * 255)
                .toString(16).padStart(2, '0')
            return color + alpha
        })

        return this.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: r.labels,
                datasets: [{
                    data: r.data,
                    backgroundColor: bgColors,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x.toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 } },
                    },
                    y: {
                        ticks: {
                            font: { size: 11 },
                            callback: (_, i) => {
                                const l = r.labels[i] ?? ''
                                return l.length > 22 ? l.slice(0, 20) + '…' : l
                            }
                        },
                        grid: { display: false },
                    }
                }
            }
        }))
    }

    /**
     * Barres verticales — résumé si > topN catégories
     */
    barV(canvas, labels, data, color, topN = 12) {
        const r = this.topN(labels, data, topN)

        return this.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: r.labels,
                datasets: [{
                    data: r.data,
                    backgroundColor: r.data.map((_, i) =>
                        r.hasOthers && i === r.data.length - 1 ? '#B4B2A9' : color
                    ),
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { font: { size: 11 }, maxRotation: 35, autoSkip: false },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 11 }, precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                    }
                }
            }
        }))
    }

    /**
     * Ligne avec zone remplie
     */
    line(canvas, labels, data, color) {
        return this.push(new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data,
                    borderColor: color,
                    backgroundColor: color + '18',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: color,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => ` ${ctx.parsed.y.toLocaleString()} document(s)` }
                    }
                },
                scales: {
                    x: {
                        ticks: { font: { size: 11 }, maxRotation: 30 },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 11 }, precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                    }
                }
            }
        }))
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════

    buildAdmin() {
        const s = this.byStatusValue
        if (this.hasChartStatusTarget && Object.keys(s).length) {
            this.doughnut(
                this.chartStatusTarget,
                document.getElementById('legend-status'),
                Object.keys(s), Object.values(s).map(Number),
                ['#888780', '#378ADD', '#639922', '#E24B4A', '#7F77DD']
            )
        }

        const c = this.byConfValue
        if (this.hasChartConfTarget && Object.keys(c).length) {
            this.doughnut(
                this.chartConfTarget,
                document.getElementById('legend-conf'),
                Object.keys(c), Object.values(c).map(Number),
                ['#1D9E75', '#EF9F27', '#E24B4A']
            )
        }

        const m = this.monthlyDepositsValue
        if (this.hasChartMonthlyTarget && m.length) {
            this.line(this.chartMonthlyTarget, m.map(r => r.mois), m.map(r => Number(r.total)), '#378ADD')
        }

        // Services — Top 10 automatique (36 directions → résumé)
        const d = this.byDirectionValue
        if (this.hasChartDirTarget && d.length) {
            this.barH(
                this.chartDirTarget,
                d.map(r => r.direction ?? 'Non affecté'),
                d.map(r => Number(r.total)),
                '#7F77DD', 10
            )
        }

        // Agences — Top 10 automatique
        const ag = this.byAgenceValue
        if (this.hasChartAgenceTarget && ag.length) {
            this.barH(
                this.chartAgenceTarget,
                ag.map(r => r.agence ?? 'Non affecté'),
                ag.map(r => Number(r.total)),
                '#1D9E75', 10
            )
        }
    }

    // ══════════════════════════════════════════════════════════════
    // RH
    // ══════════════════════════════════════════════════════════════

    buildRh() {
        const s = this.bySexeValue
        if (this.hasChartSexeTarget && Object.keys(s).length) {
            this.doughnut(
                this.chartSexeTarget,
                document.getElementById('legend-sexe'),
                Object.keys(s), Object.values(s).map(Number),
                ['#378ADD', '#D4537E', '#1D9E75', '#EF9F27']
            )
        }

        // Situation familiale — résumé si > 6 valeurs
        const f = this.byFamilleValue
        if (this.hasChartFamilleTarget && Object.keys(f).length) {
            this.barH(
                this.chartFamilleTarget,
                Object.keys(f), Object.values(f).map(Number),
                '#1D9E75', 6
            )
        }

        // Ancienneté — tranches fixes, pas de résumé nécessaire
        const a = this.byAncienneteValue
        if (this.hasChartAncienneteTarget && a.length) {
            this.barV(
                this.chartAncienneteTarget,
                a.map(r => r.tranche), a.map(r => Number(r.total)),
                '#EF9F27', 10
            )
        }

        // Services — Top 8
        const d = this.byDirectionValue
        if (this.hasChartDirTarget && d.length) {
            this.barH(
                this.chartDirTarget,
                d.map(r => r.direction ?? 'Non affecté'),
                d.map(r => Number(r.total)),
                '#7F77DD', 8
            )
        }

        // Agences — Top 8
        const ag = this.byAgenceValue
        if (this.hasChartAgenceTarget && ag.length) {
            this.barH(
                this.chartAgenceTarget,
                ag.map(r => r.agence ?? 'Non affecté'),
                ag.map(r => Number(r.total)),
                '#EF9F27', 8
            )
        }
    }

    // ══════════════════════════════════════════════════════════════
    // CHEF
    // ══════════════════════════════════════════════════════════════

    buildChef() {
        const t = this.archivesByTypeValue
        if (this.hasChartTypeTarget && Object.keys(t).length) {
            this.barH(
                this.chartTypeTarget,
                Object.keys(t), Object.values(t).map(Number),
                '#534AB7', 8
            )
        }

        const m = this.archivesMonthlyValue
        if (this.hasChartMonthlyChefTarget && m.length) {
            this.line(this.chartMonthlyChefTarget, m.map(r => r.mois), m.map(r => Number(r.total)), '#534AB7')
        }
    }

    // ══════════════════════════════════════════════════════════════
    // SAP
    // ══════════════════════════════════════════════════════════════

    buildSap() {
        const q = this.queueWeeklyValue
        if (this.hasChartQueueTarget && q.length) {
            this.push(new Chart(this.chartQueueTarget, {
                type: 'line',
                data: {
                    labels: q.map(r => r.jour),
                    datasets: [
                        {
                            label: 'Soumis',
                            data: q.map(r => Number(r.soumis ?? r.total ?? 0)),
                            borderColor: '#378ADD', backgroundColor: '#378ADD18',
                            fill: true, tension: 0.4, pointRadius: 4, borderDash: [5, 3],
                        },
                        {
                            label: 'Décidés',
                            data: q.map(r => Number(r.decides ?? 0)),
                            borderColor: '#639922', backgroundColor: '#63992218',
                            fill: true, tension: 0.4, pointRadius: 4,
                        },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { font: { size: 11 }, precision: 0 } },
                        x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                    }
                }
            }))
        }

        const at = this.approByTypeValue
        if (this.hasChartApproRateTarget && at.length) {
            // Résumé : Top 8 types par volume total
            const allTypes = [...new Set(at.map(r => r.type))]
            const byVolume = allTypes.map(t => ({
                type: t,
                total: at.filter(r => r.type === t).reduce((s, r) => s + Number(r.total), 0)
            })).sort((a, b) => b.total - a.total)

            const TOP = 8
            let types = byVolume.slice(0, TOP).map(x => x.type)
            let hasOthers = byVolume.length > TOP

            if (hasOthers) {
                const othersTypes = byVolume.slice(TOP).map(x => x.type)
                // Agrège "Autres" dans at
                const othersAppr = othersTypes.reduce((s, t) =>
                    s + Number(at.find(r => r.type === t && r.statut === 'Approuvé')?.total ?? 0), 0)
                const othersRej  = othersTypes.reduce((s, t) =>
                    s + Number(at.find(r => r.type === t && r.statut === 'Rejeté')?.total ?? 0), 0)
                types = [...types, `Autres (${byVolume.length - TOP})`]
                at.push({ type: `Autres (${byVolume.length - TOP})`, statut: 'Approuvé', total: othersAppr })
                at.push({ type: `Autres (${byVolume.length - TOP})`, statut: 'Rejeté',   total: othersRej  })
            }

            const approuv = types.map(t => Number(at.find(r => r.type === t && r.statut === 'Approuvé')?.total ?? 0))
            const rejetes = types.map(t => Number(at.find(r => r.type === t && r.statut === 'Rejeté')?.total  ?? 0))
            const totals  = types.map((_, i) => approuv[i] + rejetes[i])

            this.push(new Chart(this.chartApproRateTarget, {
                type: 'bar',
                data: {
                    labels: types,
                    datasets: [
                        {
                            label: 'Approuvés',
                            data: types.map((_, i) => totals[i] > 0 ? Math.round(approuv[i] / totals[i] * 100) : 0),
                            backgroundColor: '#639922cc', borderRadius: 4,
                        },
                        {
                            label: 'Rejetés',
                            data: types.map((_, i) => totals[i] > 0 ? Math.round(rejetes[i] / totals[i] * 100) : 0),
                            backgroundColor: '#E24B4Acc', borderRadius: 4,
                        },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label} : ${ctx.parsed.y}%` } }
                    },
                    scales: {
                        x: { stacked: true, ticks: { font: { size: 11 }, maxRotation: 30 }, grid: { display: false } },
                        y: {
                            stacked: true, beginAtZero: true, max: 100,
                            ticks: { font: { size: 11 }, callback: v => v + '%' },
                            grid: { color: 'rgba(0,0,0,0.04)' },
                        }
                    }
                }
            }))
        }
    }

    // ══════════════════════════════════════════════════════════════
    // USER
    // ══════════════════════════════════════════════════════════════

    buildUser() {
        const s = this.myDocsByStatusValue
        if (this.hasChartMyStatusTarget && Object.keys(s).length) {
            this.doughnut(
                this.chartMyStatusTarget,
                document.getElementById('legend-my-status'),
                Object.keys(s), Object.values(s).map(Number),
                ['#888780', '#378ADD', '#639922', '#E24B4A', '#7F77DD']
            )
        }

        const m = this.myMonthlyDepositValue
        if (this.hasChartMyMonthlyTarget && m.length) {
            this.barV(this.chartMyMonthlyTarget, m.map(r => r.mois), m.map(r => Number(r.total)), '#1D9E75')
        }
    }
}
