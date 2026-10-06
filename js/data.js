/* ============================================================
 * data.js  —  Shared utility module (Practical 6)
 * Provides: fetchJson, paginate, pageControls, renderTag
 * All other view modules import from this single file,
 * ensuring modularity: each concern lives in exactly one place.
 * ============================================================ */

/**
 * fetchJson(path)
 * Wraps the native Fetch API with a descriptive error on non-OK
 * responses.  Returns parsed JSON on success.
 *
 * @param   {string} path  - URL or relative path to a JSON file
 * @returns {Promise<any>} - Parsed JSON data
 */
export async function fetchJson(path) {
    const response = await fetch(path);
    if (!response.ok) throw new Error(`Unable to load ${path} (HTTP ${response.status})`);
    return response.json();
}

/**
 * paginate(items, page, pageSize)
 * Pure function – splits an array into pages and clamps the
 * requested page number into the valid range.
 *
 * @param   {Array}  items    - Full filtered/sorted dataset
 * @param   {number} page     - Requested page (1-based)
 * @param   {number} pageSize - Records per page (default 5)
 * @returns {{ rows, page, totalPages }}
 */
export function paginate(items, page, pageSize = 5) {
    const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
    const safePage   = Math.min(Math.max(page, 1), totalPages);
    const rows       = items.slice((safePage - 1) * pageSize, safePage * pageSize);
    return { rows, page: safePage, totalPages };
}

/**
 * pageControls(container, page, totalPages, onChange)
 * Renders Previous / Page N of M / Next buttons into a container
 * element and wires click handlers.
 *
 * @param {HTMLElement} container  - The pagination wrapper element
 * @param {number}      page       - Current active page
 * @param {number}      totalPages - Total number of pages
 * @param {Function}    onChange   - Callback(newPage) on navigation
 */
export function pageControls(container, page, totalPages, onChange) {
    container.innerHTML =
        `<button class="button button--light" data-page="prev" ${page === 1 ? "disabled" : ""}>&#8592; Prev</button>` +
        `<span>Page ${page} of ${totalPages}</span>` +
        `<button class="button button--light" data-page="next" ${page === totalPages ? "disabled" : ""}>Next &#8594;</button>`;
    container.querySelector("[data-page=prev]")?.addEventListener("click", () => onChange(page - 1));
    container.querySelector("[data-page=next]")?.addEventListener("click", () => onChange(page + 1));
}

/**
 * renderTag(text, extra)
 * Returns HTML string for a styled badge/tag.
 *
 * @param   {string} text  - Label text
 * @param   {string} extra - Optional extra CSS class
 * @returns {string}       - HTML string
 */
export function renderTag(text, extra = "") {
    return `<span class="data-tag${extra ? " " + extra : ""}">${text}</span>`;
}
