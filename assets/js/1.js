document.addEventListener("DOMContentLoaded", function () {
    const forgotPasswordLink = document.getElementById("forgotPasswordLink");
    const backToLoginLink = document.getElementById("backToLoginLink");
    const loginForm = document.getElementById("loginForm");
    const recoveryForm = document.getElementById("recoveryForm");

    // Show the recovery form and hide the login form
    forgotPasswordLink.addEventListener("click", function (e) {
        e.preventDefault();
        loginForm.style.display = "none";
        recoveryForm.style.display = "block";
    });

    // Show the login form and hide the recovery form
    backToLoginLink.addEventListener("click", function (e) {
        e.preventDefault();
        recoveryForm.style.display = "none";
        loginForm.style.display = "block";
    });
});