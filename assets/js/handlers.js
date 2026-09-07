/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import UiHandlers from "@c975l/ui-bundle/handlers.js";
import translations from "./translations.js";

export default {
    translations: translations,

    // Both read "this.translations", so borrowing them here hands them this bundle's own catalogue rather than UiBundle's
    getLanguage: UiHandlers.getLanguage,
    translate: UiHandlers.translate,

    // Displays a message in the placeholder the layout draws
    displayMessage(message, alertClass) {
        const messageElement = document.querySelector(".global-message");
        if (!messageElement) {
            return;
        }

        messageElement.className = `global-message alert ${alertClass}`;
        messageElement.textContent = message;
        messageElement.style.display = "block";
        messageElement.style.opacity = "1";
    },

    // Writes the moment a prize was drawn, in the page's own way of writing a date
    formatDate(date, locale) {
        return date.toLocaleString(locale, {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    }
};
