-- CineTrack: schema (fresh build). Works on XAMPP MariaDB 10.2+ and MySQL 8.0.16+.
-- WARNING: drops and recreates the database. For an existing database use sql/migrate.sql instead.

DROP DATABASE IF EXISTS movie_watchlist;
CREATE DATABASE movie_watchlist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE movie_watchlist;

-- Accounts. role = 'user' (own data only) or 'developer' (also gets the Developer panel).
CREATE TABLE users (
    user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(60)  NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user','developer') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    bio           TEXT         NULL,
    avatar_url    VARCHAR(500) NULL,
    is_public     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB;

CREATE TABLE movies (
    movie_id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED  NOT NULL,
    title              VARCHAR(150)  NOT NULL,
    short_description  TEXT          NOT NULL,
    release_year       SMALLINT      NOT NULL,
    genre              VARCHAR(50)   NOT NULL,
    director           VARCHAR(100)  NOT NULL,
    `cast`             VARCHAR(255)  NOT NULL,
    duration_minutes   SMALLINT      NOT NULL,
    `language`         VARCHAR(40)   NOT NULL DEFAULT 'English',
    country            VARCHAR(60)   NOT NULL,
    age_rating         VARCHAR(10)   NOT NULL,
    watch_status       ENUM('To Watch','Watching','Watched') NOT NULL DEFAULT 'To Watch',
    watch_count        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    date_added         DATE          NOT NULL DEFAULT (CURRENT_DATE),
    poster_url         VARCHAR(500)  NULL,
    user_rating        DECIMAL(3,1)  NULL,              -- NULL = not rated
    review             TEXT          NULL,
    streaming_platform VARCHAR(50)   NULL,
    watch_url          VARCHAR(500)  NULL,
    priority           ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
    favorite           TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (movie_id),
    CONSTRAINT fk_movie_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    CONSTRAINT chk_year     CHECK (release_year BETWEEN 1888 AND 2100),
    CONSTRAINT chk_duration CHECK (duration_minutes > 0),
    CONSTRAINT chk_rating   CHECK (user_rating IS NULL OR user_rating BETWEEN 0 AND 10),
    CONSTRAINT chk_favorite CHECK (favorite IN (0,1)),
    INDEX idx_user (user_id),
    INDEX idx_title (title),
    INDEX idx_genre (genre),
    INDEX idx_status (watch_status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

-- Watchlist folders: each belongs to one user
CREATE TABLE collections (
    collection_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    name          VARCHAR(60)  NOT NULL,
    description   VARCHAR(255) NULL,
    color         VARCHAR(10)  NOT NULL DEFAULT 'violet',
    icon          VARCHAR(20)  NOT NULL DEFAULT 'folder',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id),
    UNIQUE KEY uq_collection_user_name (user_id, name),
    CONSTRAINT fk_col_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE collection_movies (
    collection_id INT UNSIGNED NOT NULL,
    movie_id      INT UNSIGNED NOT NULL,
    added_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id, movie_id),
    CONSTRAINT fk_cm_collection FOREIGN KEY (collection_id) REFERENCES collections (collection_id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_movie      FOREIGN KEY (movie_id)      REFERENCES movies (movie_id)           ON DELETE CASCADE
) ENGINE=InnoDB;
