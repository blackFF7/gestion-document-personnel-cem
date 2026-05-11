import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

    connect() {

        this.isDragging = false
        this.offsetX = 0
        this.offsetY = 0

        this.pointerDownHandler = this.pointerDown.bind(this)
        this.pointerMoveHandler = this.pointerMove.bind(this)
        this.pointerUpHandler = this.pointerUp.bind(this)

        this.element.addEventListener('pointerdown', this.pointerDownHandler)

        document.addEventListener('pointermove', this.pointerMoveHandler)

        document.addEventListener('pointerup', this.pointerUpHandler)
    }

    disconnect() {

        this.element.removeEventListener('pointerdown', this.pointerDownHandler)

        document.removeEventListener('pointermove', this.pointerMoveHandler)

        document.removeEventListener('pointerup', this.pointerUpHandler)
    }

    pointerDown(event) {

        this.isDragging = true

        this.offsetX = event.clientX - this.element.offsetLeft
        this.offsetY = event.clientY - this.element.offsetTop

        this.element.style.transition = 'none'
    }

    pointerMove(event) {

        if (!this.isDragging) return

        const x = event.clientX - this.offsetX
        const y = event.clientY - this.offsetY

        const maxX = window.innerWidth - this.element.offsetWidth
        const maxY = window.innerHeight - this.element.offsetHeight

        this.element.style.left = `${Math.max(0, Math.min(x, maxX))}px`
        this.element.style.top = `${Math.max(0, Math.min(y, maxY))}px`

        this.element.style.right = 'auto'
        this.element.style.bottom = 'auto'
    }

    pointerUp() {

        this.isDragging = false

        this.element.style.transition = ''
    }
}