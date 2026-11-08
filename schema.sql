CREATE DATABASE IF NOT EXISTS web_project_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE web_project_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS items;

CREATE TABLE IF NOT EXISTS tours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    destination VARCHAR(120) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    duration_days TINYINT UNSIGNED NOT NULL,
    price_per_person DECIMAL(10, 2) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tours_name_unique (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tour_id INT UNSIGNED NOT NULL,
    travel_date DATE NOT NULL,
    guests TINYINT UNSIGNED NOT NULL,
    total_price DECIMAL(10, 2) UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY bookings_user_id_index (user_id),
    KEY bookings_tour_id_index (tour_id),
    KEY bookings_travel_date_index (travel_date),
    CONSTRAINT bookings_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT bookings_tour_id_foreign
        FOREIGN KEY (tour_id) REFERENCES tours (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO tours (name, destination, summary, description, duration_days, price_per_person)
VALUES
    (
        'Hunza Valley Escape',
        'Hunza Valley, Gilgit-Baltistan',
        'Five unhurried days among forts, glaciers, and apricot orchards.',
        'Travel the Karakoram Highway to Karimabad, walk through Baltit Fort, and watch the sun set over Rakaposhi. Mornings are slow, afternoons are for short trails, and every evening ends with mountain tea.',
        5,
        85000.00
    ),
    (
        'Skardu and Shangrila',
        'Skardu, Gilgit-Baltistan',
        'Six days of cold deserts, quiet lakes, and high mountain air.',
        'Fly or drive into Skardu and settle beside the Indus. Visit Shangrila Resort, the Katpana cold desert, and the still water of Upper Kachura Lake, with a free day for a short Deosai excursion when the road is open.',
        6,
        95000.00
    ),
    (
        'Naran and Saif-ul-Malook',
        'Naran, Kaghan Valley',
        'A four-day classic through pine forest and glacier-fed lakes.',
        'Follow the Kunhar River up the Kaghan Valley, spend a morning at Saif-ul-Malook, and take the jeep track to Lulusar. Comfortable guesthouses, short walks, and plenty of time beside the water.',
        4,
        55000.00
    ),
    (
        'Swat Valley Retreat',
        'Swat, Khyber Pakhtunkhwa',
        'Three gentle days of rivers, meadows, and river-side cafes.',
        'A relaxed introduction to Swat: Malam Jabba in the morning, the Swat River in the afternoon, and a final day in the meadows above Kalam. Ideal for a first trip to the north.',
        3,
        42000.00
    ),
    (
        'Fairy Meadows Adventure',
        'Fairy Meadows, Gilgit-Baltistan',
        'Five active days beneath Nanga Parbat, with jeep and trail travel.',
        'Drive to Raikot Bridge, take the mountain jeep to Tato, then walk to Fairy Meadows. Two nights in wooden huts, a day hike toward Beyal Camp, and an unobstructed view of Nanga Parbat at sunrise.',
        5,
        78000.00
    ),
    (
        'Neelum Valley Journey',
        'Neelum Valley, Azad Kashmir',
        'Four days following the Neelum River through quiet villages.',
        'Trace the river road from Muzaffarabad to Sharda and Kel. Stop at the Ratti Gali trailhead when the season allows, walk through old villages, and stay in family-run guesthouses along the water.',
        4,
        50000.00
    );

INSERT INTO users (username, email, password)
VALUES ('Demo User', 'demo@example.com', '$2y$10$ZMU/vNRnlOAMO0t1jF8SB.kkdkCRxPMclZe73MGP/1c9XxV5An9h6')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO bookings (user_id, tour_id, travel_date, guests, total_price, status)
SELECT
    users.id,
    tours.id,
    DATE_ADD(CURDATE(), INTERVAL 40 DAY),
    2,
    tours.price_per_person * 2,
    'confirmed'
FROM users
JOIN tours ON tours.name = 'Hunza Valley Escape'
WHERE users.email = 'demo@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM bookings
      WHERE bookings.user_id = users.id
        AND bookings.tour_id = tours.id
  );
