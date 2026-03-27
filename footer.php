<?php
/**
 * GAIMS - Main Footer File
 * Include this at the bottom of all pages
 */
?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="footer-about">
                    <img src="<?php echo SITE_URL; ?>assets/images/logo.png" alt="Greenhill Academy" class="footer-logo" style="height: 50px; margin-bottom: 20px;" onerror="this.style.display='none'">
                    <p>Greenhill Academy is a leading Christian-based educational institution in Kampala, Uganda, providing quality education from Nursery to Secondary level since 1994.</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo SITE_URL; ?>index.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>about.php">About Us</a></li>
                    <li><a href="<?php echo SITE_URL; ?>academics.php">Academics</a></li>
                    <li><a href="<?php echo SITE_URL; ?>admissions.php">Admissions</a></li>
                    <li><a href="<?php echo SITE_URL; ?>contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5>Contact Info</h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-phone-alt me-2"></i> +256 414 663680</li>
                    <li><i class="fas fa-envelope me-2"></i> info@greenhill.ac.ug</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Kibuli Campus: Mbogo Road</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Buwaate Campus: Kira-Kasangati Road</li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5>Newsletter</h5>
                <p>Subscribe for updates</p>
                <form action="<?php echo SITE_URL; ?>php/newsletter.php" method="POST" class="newsletter-form">
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" placeholder="Your Email" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <hr class="mt-4 mb-3" style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Greenhill Academy. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/custom.js"></script>

<script>
    AOS.init({
        duration: 1000,
        once: true,
        offset: 100
    });
    
    // Back to Top Button
    $(window).scroll(function() {
        if ($(this).scrollTop() > 300) {
            $('#backToTop').addClass('active');
        } else {
            $('#backToTop').removeClass('active');
        }
    });
    
    $('#backToTop').click(function(e) {
        e.preventDefault();
        $('html, body').animate({scrollTop: 0}, 500);
    });
</script>

</body>
</html>