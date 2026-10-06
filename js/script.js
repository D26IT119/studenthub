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

    document.querySelectorAll("form").forEach((form) => {
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
