-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    familyname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Places table
CREATE TABLE places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    latitude FLOAT NOT NULL,
    longitude FLOAT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Trip table
CREATE TABLE trip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    total_distance_km FLOAT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Trip_places table (junction between trip and places)
CREATE TABLE trip_places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    place_id INT NOT NULL,
    visit_order INT NOT NULL,
    FOREIGN KEY (trip_id) REFERENCES trip(id) ON DELETE CASCADE,
    FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
);

-- Shares table
CREATE TABLE shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    shared_with_user_id INT NULL,
    visibility ENUM('public', 'private') NOT NULL DEFAULT 'private',

    -- Privé => un utilisateur ciblé ; public => aucun utilisateur
    CONSTRAINT chk_private_has_user CHECK (
        (visibility = 'private' AND shared_with_user_id IS NOT NULL)
        OR
        (visibility = 'public' AND shared_with_user_id IS NULL)
    ),
    FOREIGN KEY (trip_id) REFERENCES trip(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (trip_id, shared_with_user_id)
);