document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("#secure-registration-form");
    if (!form) return;

    const rules = {
        name: { test: value => [...value].length >= 2 && [...value].length <= 80 && /^\p{L}+(?:[ .'-]\p{L}+)*$/u.test(value), message: "Enter 2 to 80 characters using letters, spaces, apostrophes, periods, or hyphens." },
        username: { test: value => /^[A-Za-z0-9_]{3,30}$/.test(value), message: "Use 3 to 30 letters, numbers, or underscores." },
        email: { test: value => value.length <= 150 && /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(value), message: "Enter a valid email address (maximum 150 characters)." },
        password: { test: value => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(value), message: "Use at least 8 characters with uppercase, lowercase, number, and symbol." },
        confirm_password: { test: value => value === form.elements.password.value, message: "The passwords do not match." }
    };

    const validate = input => {
        let message = "";
        if (rules[input.name]) {
            const value = ["name", "username", "email"].includes(input.name) ? input.value.trim() : input.value;
            if (!rules[input.name].test(value)) message = rules[input.name].message;
        } else if (input.name === "terms" && !input.checked) {
            message = "Accept the terms and privacy policy to continue.";
        }
        input.classList.toggle("input-error", Boolean(message));
        input.setAttribute("aria-invalid", String(Boolean(message)));
        const error = document.getElementById(`${input.id}-error`);
        if (error) error.textContent = message;
        return !message;
    };

    form.querySelectorAll("input:not([type=hidden])").forEach(input => {
        input.addEventListener("blur", () => validate(input));
        input.addEventListener("input", () => {
            if (input.classList.contains("input-error")) validate(input);
            if (input.name === "password" && form.elements.confirm_password.value) validate(form.elements.confirm_password);
        });
        input.addEventListener("change", () => validate(input));
    });
    form.addEventListener("submit", event => {
        const valid = [...form.querySelectorAll("input:not([type=hidden])")].map(validate).every(Boolean);
        if (!valid) {
            event.preventDefault();
            form.querySelector(".input-error")?.focus();
        }
    });
});
