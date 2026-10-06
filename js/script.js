document.addEventListener("DOMContentLoaded", () => {
    const footer = document.querySelector("footer");
    if (footer) footer.innerHTML = `&copy; ${new Date().getFullYear()} StudentHub <span aria-hidden="true">&middot;</span> Your campus, connected`;

    const themeToggle = document.querySelector("[data-theme-toggle]");
    const savedTheme = localStorage.getItem("studenthub-theme");
    if (savedTheme === "dark") document.body.classList.add("dark-theme");
    const updateThemeIcon = () => {
        if (themeToggle) themeToggle.textContent = document.body.classList.contains("dark-theme") ? "☀" : "☾";
    };
    updateThemeIcon();
    themeToggle?.addEventListener("click", () => {
        document.body.classList.toggle("dark-theme");
        localStorage.setItem("studenthub-theme", document.body.classList.contains("dark-theme") ? "dark" : "light");
        updateThemeIcon();
    });

    const menuToggle = document.querySelector("[data-menu-toggle]");
    const nav = document.querySelector(".site-nav");
    menuToggle?.addEventListener("click", () => {
        const open = nav.classList.toggle("is-open");
        menuToggle.setAttribute("aria-expanded", open);
    });

    document.querySelector("[data-dismiss-banner]")?.addEventListener("click", (event) => {
        event.currentTarget.closest(".announcement")?.remove();
    });

    document.querySelectorAll(".faq-question").forEach((button) => {
        button.addEventListener("click", () => {
            const item = button.closest(".faq-item");
            const open = !item.classList.contains("is-open");
            document.querySelectorAll(".faq-item").forEach((faqItem) => {
                faqItem.classList.remove("is-open");
                faqItem.querySelector(".faq-question").setAttribute("aria-expanded", "false");
                faqItem.querySelector(".faq-question span").textContent = "+";
            });
            if (open) item.classList.add("is-open");
            button.setAttribute("aria-expanded", open);
            button.querySelector("span").textContent = open ? "−" : "+";
        });
    });

    const modal = document.querySelector(".modal");
    const closeModal = () => modal?.classList.remove("is-open");
    document.querySelectorAll("[data-open-modal]").forEach((button) => button.addEventListener("click", () => modal?.classList.add("is-open")));
    document.querySelectorAll("[data-close-modal]").forEach((button) => button.addEventListener("click", closeModal));
    modal?.addEventListener("click", (event) => { if (event.target === modal) closeModal(); });
    document.addEventListener("keydown", (event) => { if (event.key === "Escape") closeModal(); });

    const slides = [...document.querySelectorAll(".slide")];
    const dots = [...document.querySelectorAll(".slider-dot")];
    let activeSlide = 0;
    const showSlide = (index) => {
        if (!slides.length) return;
        activeSlide = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => slide.classList.toggle("is-active", i === activeSlide));
        dots.forEach((dot, i) => dot.classList.toggle("is-active", i === activeSlide));
    };
    document.querySelector("[data-slider-prev]")?.addEventListener("click", () => showSlide(activeSlide - 1));
    document.querySelector("[data-slider-next]")?.addEventListener("click", () => showSlide(activeSlide + 1));
    dots.forEach((dot, index) => dot.addEventListener("click", () => showSlide(index)));
    if (slides.length > 1) setInterval(() => showSlide(activeSlide + 1), 5000);

    const registrationForm = document.querySelector("#registration-form");
    if (registrationForm) {
        const fields = {
            name: { pattern: /^[A-Za-z]+(?:[ .'-][A-Za-z]+)+$/, message: "Enter your first and last name using letters only." },
            email: { pattern: /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/, message: "Enter a valid email address." },
            mobile: { pattern: /^[6-9]\d{9}$/, message: "Enter a valid 10-digit Indian mobile number." }
        };
        const setError = (input, message) => {
            const error = document.querySelector(`#${input.id}-error`);
            input.classList.toggle("input-error", Boolean(message));
            input.setAttribute("aria-invalid", Boolean(message));
            if (error) error.textContent = message || "";
            return !message;
        };
        const validateField = (input) => {
            const rule = fields[input.name];
            if (rule) return setError(input, rule.pattern.test(input.value.trim()) ? "" : rule.message);
            if (input.name === "course" || input.name === "year") return setError(input, input.value ? "" : "Please select an option.");
            if (input.name === "confirmPassword") return setError(input, input.value === document.querySelector("#password").value ? "" : "Passwords do not match.");
            if (input.name === "password") {
                const valid = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(input.value);
                return setError(input, valid ? "" : "Use 8+ characters with uppercase, lowercase, number and symbol.");
            }
            return true;
        };
        const updateStrength = () => {
            const password = document.querySelector("#password").value;
            const score = [password.length >= 8, /[a-z]/.test(password), /[A-Z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)].filter(Boolean).length;
            const meter = document.querySelector("#password-strength");
            const label = document.querySelector("#strength-label");
            const labels = ["", "Very weak", "Weak", "Fair", "Good", "Strong"];
            if (meter) { meter.value = score; meter.className = `strength-${score}`; }
            if (label) label.textContent = password ? labels[score] : "Start typing to check strength";
        };
        registrationForm.querySelectorAll("input, select").forEach((input) => {
            input.addEventListener("blur", () => validateField(input));
            input.addEventListener("input", () => { if (input.name === "password") updateStrength(); if (input.classList.contains("input-error")) validateField(input); });
        });
        registrationForm.addEventListener("submit", (event) => {
            event.preventDefault();
            const validInputs = [...registrationForm.querySelectorAll("input:not([type=radio]):not([type=checkbox]), select")].map(validateField);
            const gender = registrationForm.querySelector("input[name=gender]:checked");
            const genderError = document.querySelector("#gender-error");
            genderError.textContent = gender ? "" : "Please select your gender.";
            const terms = document.querySelector("#terms");
            const termsError = document.querySelector("#terms-error");
            termsError.textContent = terms.checked ? "" : "You must accept the terms to continue.";
            if (validInputs.every(Boolean) && gender && terms.checked) {
                const message = document.querySelector("#registration-success");
                message.textContent = "Registration successful! Your StudentHub account is ready.";
                registrationForm.reset();
                updateStrength();
            }
        });
    }

    document.querySelectorAll("form").forEach((form) => {
        if (form.id === "registration-form") return;
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            if (!form.checkValidity()) return form.reportValidity();
            const message = document.createElement("p");
            message.className = "form-message";
            message.setAttribute("role", "status");
            message.textContent = "Thanks! Your information has been saved for this demo.";
            form.querySelector(".form-message")?.remove();
            form.append(message);
            form.reset();
        });
    });
});
