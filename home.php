<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT profile, first_name FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$profile_image = $user['profile'] ?? '';
$first_name = $user['firstname'] ?? '';

$announcement_query = "
    SELECT a.announcement, a.admin_id, ad.first_name,ad.last_name 
    FROM announcements a
    JOIN admin ad ON a.admin_id = ad.id
    ORDER BY a.created_at DESC LIMIT 1
";
$announcement_result = $conn->query($announcement_query);
$announcement = $announcement_result->fetch_assoc();
$recent_announcement = $announcement['announcement'] ?? '';
$admin_username = $announcement['first_name'] . ' ' . $announcement['last_name'] ?? '';

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/home.css" />
    <link rel="stylesheet" href="./css/bellring.css" />
</head>

<body>

    <?php include './layout/header.php' ?>

    <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
        <ol class="carousel-indicators">
            <li data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active"></li>
            <li data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1"></li>
            <li data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2"></li>
        </ol>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="../img/coverimage1.webp" class="d-block w-100" style="max-height: 50vh; object-fit: cover;" alt="First slide">
                <div class="carousel-caption d-none d-md-block">
                    <h1 class="bg-light text-dark p-2">To-Do Management</h1>
                    <p class="bg-warning fw-bold fs-5">Organize and prioritize your tasks efficiently.</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="../img/coverimage2.jpg" class="d-block w-100" style="max-height: 50vh; object-fit: cover;" alt="Second slide">
                <div class="carousel-caption d-none d-md-block">
                    <h1 class="bg-light text-dark p-2">Task Handling</h1>
                    <p class="bg-warning fw-bold fs-5">Manage your workload with ease and confidence.</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="../img/coverimage3.jpg" class="d-block w-100" style="max-height: 50vh; object-fit: cover;" alt="Third slide">
                <div class="carousel-caption d-none d-md-block">
                    <h1 class="bg-light text-dark p-2">Task Efficiency</h1>
                    <p class="bg-warning fw-bold fs-5">Boost productivity by handling tasks effectively.</p>
                </div>
            </div>
        </div>
        <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </a>
        <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </a>
    </div>

    <div class="container mb-5" style="max-height: 50vh;">
        <h1 class="text-center text-danger mt-5 mb-4">Annoucement! <i class="fa-regular fa-bell text-danger fs-1 ringing-bell"></i></h1>
        <?php if (!empty($recent_announcement)): ?>
            <div class="alert alert-warning text-center fs-4 p-3 border border-danger border-2">
                <p><?= htmlspecialchars($recent_announcement) ?></p>
                <small class="text-muted">Announced by: <span class="fw-bold text-success"><?= htmlspecialchars($admin_username) ?></span> </small>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">No recent announcements.</p>
        <?php endif; ?>
    </div>

    <div class="hero-section">
        <div class="container d-flex flex-column flex-md-row align-items-center">
            <div class="hero-text p-4">
                <h1 class="display-4">Welcome to Our Service</h1>
                <p class="lead">Delivering exceptional results with efficiency and care. Explore our offerings and see how we can help you achieve your goals.</p>
                <a href="#learn-more" class="btn btn-primary">Learn More</a>
            </div>
            <div class="hero-image d-none d-md-block">
                <img src="../img/hero1.jpg" alt="Hero Image" class="img-fluid" style="border-radius: 10px;">
            </div>
        </div>
    </div>

    <div id="contact" class="contact-section">
        <div class="contact-image">
            <img src="../img/contact.avif" alt="Contact Image" style="border-radius: 10px;">
        </div>
        <div class="contact-form-container">
            <h2>Contact Us</h2>
            <form method="POST" action="sendMail.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <input type="text" name="name" class="form-control" placeholder="Your Name" id="name" required>
                </div>
                <div class="mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Your Email" id="email" required>
                </div>
                <div class="mb-3">
                    <textarea name="message" class="form-control" id="message" placeholder="Message" rows="4" required></textarea>
                </div>
                <div class="mb-3 col-6">
                    <input type="file" name="attachments[]" class="form-control" id="attachment">
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>


            <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalBody"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const modalBody = document.getElementById('modalBody');
            const responseModal = new bootstrap.Modal(document.getElementById('responseModal'));

            if (status === 'success') {
                modalBody.innerHTML = '<div class="alert alert-success">Support team will contact you very soon!</div>';
                responseModal.show();
            } else if (status === 'error') {
                const fields = urlParams.get('fields');
                let errorMessage = 'Sorry for the inconvenience';

                if (fields) {
                    const fieldArray = fields.split(',');
                    errorMessage += '<br> Fill cannot be empty: <ul>';
                    fieldArray.forEach(field => {
                        errorMessage += `<li>${field.charAt(0).toUpperCase() + field.slice(1)} is required.</li>`;
                    });
                    errorMessage += '</ul>';
                } else {
                    errorMessage += 'Please try again.';
                }

                modalBody.innerHTML = `<div class="alert alert-danger">${errorMessage}</div>`;
                responseModal.show();
            }
        });
    </script>

</body>

</html>