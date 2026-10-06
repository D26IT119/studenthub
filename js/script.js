document.addEventListener("DOMContentLoaded", () => {
    const footer = document.querySelector("footer");
    if (footer) {
        footer.innerHTML = `&copy; ${new Date().getFullYear()} StudentHub <span aria-hidden="true">&middot;</span> Your campus, connected`;
    }

    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

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
