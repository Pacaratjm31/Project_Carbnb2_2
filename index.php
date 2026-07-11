<?php
// Simple Car Rental Homepage

// Example data (replace with database later)
$cars = [
    ["model" => "Toyota Vios", "price" => 1500, "img" => "vios.jpg"],
    ["model" => "Honda Civic", "price" => 2000, "img" => "civic.jpg"],
    ["model" => "Ford Ranger", "price" => 2500, "img" => "ranger.jpg"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Carbnb - Car Rental</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        h1 { text-align: center; color: #333; }
        .cars { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .car { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); width: 220px; text-align: center; }
        .car img { width: 100%; height: 120px; object-fit: cover; border-radius: 5px; }
        .price { color: #007BFF; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Welcome to Carbnb Car Rental</h1>
    <div class="cars">
        <?php foreach ($cars as $car): ?>
            <div class="car">
                <img src="<?php echo $car['img']; ?>" alt="<?php echo $car['model']; ?>">
                <h3><?php echo $car['model']; ?></h3>
                <p class="price">₱<?php echo number_format($car['price']); ?> / day</p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
