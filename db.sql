CREATE TABLE IF NOT EXISTS users (
    id int(11) NOT NULL AUTO_INCREMENT,
    username TEXT NOT NULL,
    fullname TEXT NOT NULL,
    password TEXT NOT NULL,
    email TEXT NOT NULL,
    birthdate DATE NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS esemenyek (
    id int(11) NOT NULL AUTO_INCREMENT,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    time_start DATETIME NOT NULL,
    time_end DATETIME NOT NULL,
    restriction int(11) NOT NULL,
    description TEXT NOT NULL,
    place TEXT NOT NULL,
    city TEXT NULL,
    created_by TEXT NULL,
    PRIMARY KEY (id)
);