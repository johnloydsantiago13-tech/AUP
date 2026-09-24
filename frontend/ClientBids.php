<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo-group">
            <div class="logo-box">AUP</div>
            <h2>AUProject</h2>
        </div>
    </nav>


    <div class="main-content">
        <div class="sidebar">
            <div class="nav-links">
            <a href="Client.php">Dashboard</a>
            <a href="ClientProfile.php">Profile</a>
            <a href="ClientPostProject.php">Post Projects</a>
            <a href="ClientBids.php">My Projects</a>
            <a href="../frontend/index.php">Logout</a>
            </div>
        </div>
        <div class="my-project">
            <h2>My Project</h2>
            <p>Here u gonna check Project/bids</p>
            <div class="my-project-box">
                <div class="project-Head">
                    <div class="pcard-right"><h2>Project Title</h2></div>
                    <div class= "pcard-left">
                    <button type="submit" name ="delete">&#x1F5D1</button>
                    </div>
                </div>

                <div class="project-Details">
                    <div class="pro-Details">
                        <h2>Description</h2>
                        <p>kaya pa</p>
                    </div>
                    <div class="PBD">
                    <div class="project-budget">
                        <h3>Budget</h3>
                        <strong>$200</strong>
                    </div>
                    <div class="project-deadline">
                        <h3>Deadline</h3>
                        <strong>12-11-2026</strong>
                    </div>
                    </div>
                    <div class="pro-post">
                        <h5>Project Posted</h5>
                    </div>
                </div>

                <div class="bid-auction">
                    <h3>Project Bids</h3>            
                </div>
            </div>
        </div>
    </div>
</body>
</html>