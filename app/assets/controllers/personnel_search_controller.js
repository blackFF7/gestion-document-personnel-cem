import { Controller } from "@hotwired/stimulus"

export default class extends Controller {

    static targets = [
        "input",
        "item",
        "mobileItem"
    ]

    filter() {

        const query = this.inputTarget.value.toLowerCase()

        this.itemTargets.forEach((item) => {

            const visible = item.textContent
                .toLowerCase()
                .includes(query)

            item.style.display = visible ? "" : "none"

        })

        this.mobileItemTargets.forEach((item) => {

            const visible = item.textContent
                .toLowerCase()
                .includes(query)

            item.style.display = visible ? "" : "none"

        })

    }

}
