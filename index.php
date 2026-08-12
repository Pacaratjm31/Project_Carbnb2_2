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

nav h2 {
    color: #ffffff;
    font-size: 28px;
}

.brand-logo {
    font-size: 28px;
    font-weight: 700;
}

.brand-logo .blue,
.header-text .blue {
    color: #00bfff;
}

.brand-logo .orange,
.header-text .orange {
    color: #ff8c00;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}

.nav-links a {
    color: #cfcfcf;
    text-decoration: none;
    font-weight: bold;
    transition: color 0.3s ease;
}

.nav-links a:hover { 
    color: #00bfff; 
}

/* ✅ NEW: Navbar Download Button */
.nav-download-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ff8c00;
    color: #1a1a1a !important;
    padding: 8px 18px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid #ff8c00;
}

.nav-download-btn:hover {
    background: #e07b00 !important;
    transform: scale(1.05);
    color: #1a1a1a !important;
}

.nav-download-btn:active {
    transform: scale(0.95);
}

/* TITLE SECTION (ABOVE IMAGE) */
.header-text {
    margin-top: 100px;
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
    margin-top: 8px;
}

/* HERO BANNER */
.hero {
    position: relative;
    height: 60vh;
    margin-top: 20px;
    border-radius: 24px;
    overflow: hidden;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-color: #0b233a;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    transition: background-image 1s ease-in-out;
}

.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(11,40,66,0.38) 0%, rgba(11,40,66,0.72) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    padding: 0 24px;
    max-width: 920px;
}

.hero-content h2 {
    font-size: 3rem;
    margin-bottom: 14px;
}

.hero-content p {
    font-size: 1.2rem;
    line-height: 1.6;
    color: #e2e8f0;
    margin-bottom: 0;
}

.showcase {
    display: grid;
    grid-template-columns: repeat(3, minmax(220px, 1fr));
    gap: 20px;
    padding: 40px 20px;
    max-width: 1200px;
    margin: auto;
}

.showcase-card {
    background: #1f2937;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.showcase-card img {
    width: 100%;
    display: block;
    object-fit: cover;
    height: 220px;
}

.showcase-card-content {
    padding: 20px;
}

.showcase-card h3 {
    color: #ffd700;
    margin-bottom: 10px;
}

.showcase-card p {
    color: #d1d5db;
    line-height: 1.7;
}

/* INTRODUCTION */
.introduction {
    text-align: center;
    padding: 30px 20px;
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
    margin-top: 20px;
}

/* MOBILE ADJUSTMENTS */
@media (max-width: 768px) {
    nav {
        padding: 0 16px;
        height: 64px;
    }

    .nav-links {
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .nav-links a {
        font-size: 13px;
    }

    /* ✅ Mobile navbar button adjustment */
    .nav-download-btn {
        padding: 6px 14px;
        font-size: 0.8rem;
        gap: 4px;
    }

    .header-text {
        margin-top: 90px;
    }

    .header-text h1 { font-size: 2.2rem; }
    .header-text p { font-size: 1rem; }

    .hero-carousel {
        height: 45vh;
    }

    .hero-content h2 {
        font-size: 2rem;
    }

    .hero-content p {
        font-size: 1rem;
    }

    .carousel-controls {
        width: calc(100% - 32px);
    }

    .carousel-button {
        width: 38px;
        height: 38px;
    }

    .carousel-indicators {
        bottom: 16px;
    }

    .showcase {
        grid-template-columns: repeat(2, minmax(180px, 1fr));
        gap: 16px;
        padding: 30px 16px;
    }

    .showcase-card img {
        height: 200px;
    }

    .introduction p { padding: 10px; }
}

@media (max-width: 576px) {
    nav {
        padding: 0 12px;
        height: 58px;
    }

    .nav-links {
        gap: 8px;
    }

    .nav-links a {
        font-size: 11px;
    }

    /* ✅ Mobile navbar button adjustment */
    .nav-download-btn {
        padding: 5px 12px;
        font-size: 0.7rem;
        gap: 3px;
        border-width: 1.5px;
    }

    .header-text {
        margin-top: 80px;
    }

    .header-text h1 { font-size: 1.8rem; }
    .header-text p { font-size: 0.95rem; }

    .hero-carousel {
        height: 35vh;
    }

    .hero-content h2 {
        font-size: 1.6rem;
    }

    .hero-content p {
        font-size: 0.95rem;
    }

    .carousel-button {
        width: 34px;
        height: 34px;
    }

    .carousel-indicator {
        width: 10px;
        height: 10px;
    }

    .showcase {
        grid-template-columns: 1fr;
        padding: 24px 12px;
    }

    .showcase-card img {
        height: 180px;
    }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav>
    <h2 class="brand-logo"><span class="blue">Car</span><span class="orange">bnb</span></h2>
    <div class="nav-links">
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="auth/login.php">Login</a>
        <a href="auth/register.php">Register</a>
        <!-- ✅ NEW: Navbar Download Button -->
        <a href="carbnb_install.php" class="nav-download-btn">📱 Download App</a>
    </div>
</nav>

<!-- TITLE + SUBTITLE -->
<section class="header-text">
    <h1>
        <span class="blue">Car</span><span class="orange">bnb</span>
    </h1>
    <p>A Self-Drive Rental Platform for Private Vehicle Owners</p>
</section>

<!-- HERO BANNER -->
<section class="hero" id="hero">
    <div class="hero-content">
        <h2>Drive Luxury. Rent Easily.</h2>
        <p>Discover premium vehicles from trusted owners with effortless self-drive booking.</p>
        <!-- ❌ REMOVED: Large download button from hero section -->
    </div>
</section>

<section class="showcase">
    <div class="showcase-card">
        <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=900&q=80" alt="Luxury sports car">
        <div class="showcase-card-content">
            <h3>Luxury Collection</h3>
            <p>Browse exclusive premium cars for city cruising, weekend escapes, and special events.</p>
        </div>
    </div>
    <div class="showcase-card">
        <img src="https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=900&q=80" alt="Modern SUV">
        <div class="showcase-card-content">
            <h3>Spacious SUVs</h3>
            <p>Choose roomy and comfortable SUVs designed for family trips and long-distance travel.</p>
        </div>
    </div>
    <div class="showcase-card">
        <img src="https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=900&q=80" alt="Convertible car">
        <div class="showcase-card-content">
            <h3>Sporty Rides</h3>
            <p>Enjoy high-performance cars with sleek styling and advanced driving dynamics.</p>
        </div>
    </div>
</section>

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

<script>
const hero = document.getElementById("hero");

const images = [
    "https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1400&q=80",
    "https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=1400&q=80",
    "https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=1400&q=80"
];

let current = 0;

function updateHeroImage(index) {
    hero.style.opacity = "0";

    setTimeout(() => {
        hero.style.backgroundImage = `url("${images[index]}")`;
        hero.style.opacity = "1";
    }, 300);
}

function nextImage() {
    current = (current + 1) % images.length;
    updateHeroImage(current);
}

// Initial image
hero.style.backgroundImage = `url("${images[0]}")`;
hero.style.transition = "opacity 0.7s ease-in-out";

// Start slideshow
setInterval(nextImage, 9000);
</script>

</body>
</html>