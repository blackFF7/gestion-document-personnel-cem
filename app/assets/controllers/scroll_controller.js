import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['item']

    observer = null

    connect() {
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('scroll-visible')
                        entry.target.classList.remove('scroll-hidden')
                    } else {
                        // fade-out quand l'élément quitte le viewport
                        entry.target.classList.add('scroll-hidden')
                        entry.target.classList.remove('scroll-visible')
                    }
                })
            },
            {
                threshold: 0.08,      // déclenche dès que 8% est visible
                rootMargin: '0px 0px -40px 0px', // légèrement avant le bord bas
            }
        )

        // Observe tous les items déclarés comme targets
        this.itemTargets.forEach((el, i) => {
            // Délai en cascade : chaque item attend un peu plus que le précédent
            el.style.transitionDelay = `${i * 60}ms`
            el.classList.add('scroll-hidden')
            this.observer.observe(el)
        })
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect()
            this.observer = null
        }
    }
}
