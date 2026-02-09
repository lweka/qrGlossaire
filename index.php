<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container">
    <nav class="navbar">
        <a class="brand" href="<?= $baseUrl; ?>/">
            <img src="<?= $baseUrl; ?>/assets/images/Logo12.png" alt="Cartelplus Congo">
            <strong>Invite<span>QR</span></strong>
        </a>
        <div class="nav-links">
            <a href="#features">Fonctionnalités</a>
            <a href="#templates">Templates</a>
            <a href="#temoignages">Témoignages</a>
            <a href="#faq">FAQ</a>
        </div>
        <div>
            <a class="button ghost" href="<?= $baseUrl; ?>/login.php">Se connecter</a>
            <a class="button primary" href="<?= $baseUrl; ?>/register.php">Créer mon invitation</a>
        </div>
    </nav>
</section>

<header class="hero">
    <div class="container hero-grid">
        <div>
            <div class="badge">Plateforme professionnelle</div>
            <h1 class="fade-up">Des invitations numériques élégantes avec validation QR Code</h1>
            <p class="fade-up delay-1">Créez des expériences mémorables pour vos cérémonies. Personnalisez vos invitations, automatisez les confirmations et scannez les QR codes à l'entrée en quelques secondes.</p>
            <div class="fade-up delay-2">
                <a class="button primary" href="<?= $baseUrl; ?>/register.php">Démarrer maintenant</a>
                <a class="button ghost" href="<?= $baseUrl; ?>/invitation.php">Voir une invitation</a>
            </div>
            <div class="stats fade-up delay-3">
                <div class="stat">
                    <h4 data-count="2500">0</h4>
                    <span>Invitations créées</span>
                </div>
                <div class="stat">
                    <h4 data-count="98">0</h4>
                    <span>% de confirmations</span>
                </div>
                <div class="stat">
                    <h4 data-count="120">0</h4>
                    <span>Organisateurs actifs</span>
                </div>
            </div>
        </div>
        <div class="hero-card fade-up delay-2">
            <div class="hero-slider" data-slider>
                <div class="slide active">
                    <img src="https://images.unsplash.com/photo-1529634806980-85c3dd6d34ac?auto=format&fit=crop&w=1200&q=80" alt="Invitation mariage">
                </div>
                <div class="slide">
                    <img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=1200&q=80" alt="Anniversaire">
                </div>
                <div class="slide">
                    <img src="https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=1200&q=80" alt="Cérémonie élégante">
                </div>
                <div class="slide">
                    <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1200&q=80" alt="Événement corporate">
                </div>
                <div class="slide">
                    <img src="https://images.unsplash.com/photo-1524502397800-2eeaad7c3fe5?auto=format&fit=crop&w=1200&q=80" alt="Soirée VIP">
                </div>
                <div class="slide">
                    <img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=1200&q=80" alt="Célébration premium">
                </div>
                <div class="hero-slider-dots" data-dots></div>
            </div>
        </div>
    </div>
</header>

<section id="features" class="section">
    <div class="container">
        <div class="section-title">
            <span>Fonctionnalités</span>
            <h2>Une suite complète pour vos cérémonies</h2>
        </div>
        <div class="card-grid">
            <article class="card">
                <h3>Création intuitive</h3>
                <p>Composez une invitation en quelques minutes : templates premium, palettes personnalisées et éditeur de messages.</p>
            </article>
            <article class="card">
                <h3>Gestion d'invités</h3>
                <p>Import CSV/Excel, suivi des confirmations, relances automatiques et statistiques d'ouverture.</p>
            </article>
            <article class="card">
                <h3>QR Code sécurisé</h3>
                <p>Chaque invité reçoit un QR code unique pour une validation rapide et fiable le jour J.</p>
            </article>
            <article class="card">
                <h3>Tableaux de bord</h3>
                <p>Suivez les confirmations, les présences et les analytics détaillées en temps réel.</p>
            </article>
        </div>
    </div>
</section>

<section id="templates" class="section">
    <div class="container">
        <div class="section-title">
            <span>Templates</span>
            <h2>Des designs adaptés à chaque événement</h2>
        </div>
        <div class="gallery">
            <div class="gallery-item">
                <h4>Mariage Élégant</h4>
                <p>Typographies raffinées et photos immersives.</p>
            </div>
            <div class="gallery-item">
                <h4>Anniversaire Moderne</h4>
                <p>Couleurs vibrantes et animations fluides.</p>
            </div>
            <div class="gallery-item">
                <h4>Corporate Premium</h4>
                <p>Structure sobre et branding professionnel.</p>
            </div>
            <div class="gallery-item">
                <h4>Soirée VIP</h4>
                <p>Effets lumineux et ambiance exclusive.</p>
            </div>
        </div>
    </div>
</section>

<section id="temoignages" class="section">
    <div class="container">
        <div class="section-title">
            <span>Témoignages</span>
            <h2>Ils nous font confiance</h2>
        </div>
        <div class="testimonial">
            <div class="testimonial-card">
                <p>"Une plateforme claire et puissante. Nos invités ont adoré l'expérience et le QR code a fluidifié l'accueil."</p>
                <strong>Clarisse M., Organisatrice de mariage</strong>
            </div>
            <div class="testimonial-card">
                <p>"Tout est prêt en quelques clics. Les relances automatiques nous ont fait gagner un temps précieux."</p>
                <strong>Francis D., Event corporate</strong>
            </div>
            <div class="testimonial-card">
                <p>"Le design et les animations donnent une vraie touche premium à nos événements."</p>
                <strong>Patrick A., Agence événementielle</strong>
            </div>
        </div>
    </div>
</section>

<section id="faq" class="section">
    <div class="container">
        <div class="section-title">
            <span>FAQ</span>
            <h2>Questions fréquentes</h2>
        </div>
        <div class="faq-item">
            <button type="button">Puis-je gérer plusieurs événements ? <span>+</span></button>
            <p>Oui, votre tableau de bord permet de créer et suivre autant d'événements que nécessaire.</p>
        </div>
        <div class="faq-item">
            <button type="button">Le QR code est-il sécurisé ? <span>+</span></button>
            <p>Chaque QR code est unique et lié à l'invité, avec un hash sécurisé côté serveur.</p>
        </div>
        <div class="faq-item">
            <button type="button">Puis-je personnaliser mes emails ? <span>+</span></button>
            <p>Oui, les modèles d'emails et SMS sont entièrement personnalisables dans l'espace organisateur.</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
