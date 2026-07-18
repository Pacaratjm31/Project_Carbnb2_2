<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carbnb</title>

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0d6efd">
<script src="/pwa.js" defer></script>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
   background:#1e1e1e;
    color: #cfcfcf;
}

/* NAVBAR */
nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #2a2a2a;
    padding: 0 50px;
    height: 80px;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
}

nav h2 { color: #ffd700; font-size: 28px; }

.nav-links a {
    color: #cfcfcf;
    text-decoration: none;
    margin-left: 25px;
    font-weight: bold;
}

.nav-links a:hover { color: #00bfff; }

/* TITLE SECTION (ABOVE IMAGE) */
.header-text {
    margin-top: 100px; /* reduced from 110px */
    text-align: center;
}

.header-text h1 {
    font-size: 4rem;
    font-weight: bold;
}

.header-text .blue { color: #00bfff; }
.header-text .orange { color: #ff8c00; }

.header-text p {
    font-size: 1.3rem;
    color: #dcdcdc;
    margin-top: 8px; /* reduced spacing */
}

/* IMAGE SECTION */
.hero {
    height: 55vh; /* slightly reduced for balance */
    margin-top: 20px;
    background-image: url('image/Carbnb_logo.png');
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
}

/* INTRODUCTION */
.introduction {
    text-align: center;
    padding: 30px 20px; /* reduced from 50px */
}

.introduction h2 {
    color: #ffd700;
    margin-bottom: 10px;
}

.introduction p {
    max-width: 900px;
    margin: auto;
    line-height: 1.8;
}

/* FOOTER */
footer {
    background-color: #2a2a2a;
    color: #ffd700;
    text-align: center;
    padding: 20px;
    margin-top: 20px; /* reduced */
}

/* MOBILE ADJUSTMENTS */
@media (max-width: 768px) {
    nav {
        padding: 0 16px;
        height: 64px;
    }

    .nav-links a { margin-left: 12px; font-size: 14px; }

    .header-text h1 { font-size: 2.2rem; }
    .header-text p { font-size: 1rem; }

    .hero { height: 40vh; }
    .introduction p { padding: 10px; }
}

@media (max-width: 420px) {
    .nav-links { display: flex; gap: 8px; flex-wrap: wrap; }
    .header-text h1 { font-size: 1.8rem; }
    .hero { height: 30vh; background-size: 70%; }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav>
    <h2>Carbnb</h2>
    <div class="nav-links">
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="auth/login.php">Login</a>
        <a href="auth/register.php">Register</a>
    </div>
</nav>

<!-- TITLE + SUBTITLE -->
<section class="header-text">
    <h1>
        <span class="blue">Car</span><span class="orange">bnb</span>
    </h1>
    <p>A Self-Drive Rental Platform for Private Vehicle Owners</p>
</section>

<!-- IMAGE -->
<section class="hero"></section>

<!-- INTRODUCTION -->
<section class="introduction">
    <h2>Introduction</h2>
    <p>
        Carbnb is a web-based self-drive rental platform designed to modernize vehicle rental services.
        It allows users to browse vehicles, check availability, and book easily while helping owners manage cars and income efficiently.
    </p>
</section>

<!-- FOOTER -->
<footer>
    &copy; 2026 Carbnb. All rights reserved.
</footer>

</body>
</html>