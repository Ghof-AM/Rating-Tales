// animation.js

document.addEventListener('DOMContentLoaded', () => {
    // Find all elements with class 'form-container'
    const formContainers = document.querySelectorAll('.form-container');

    // Wait a bit before adding the 'show' class
    // This gives the browser time to render the initial layout before starting the transition
    setTimeout(() => {
        formContainers.forEach(container => {
            container.classList.add('show');
        });
    }, 100); // Wait 100ms
});