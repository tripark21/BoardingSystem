-- Make sure schema exists first
CREATE SCHEMA IF NOT EXISTS boarding;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.users (
    id         SERIAL PRIMARY KEY,
    username   VARCHAR(80)  UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       VARCHAR(10)  NOT NULL DEFAULT 'tenant' CHECK (role IN ('admin','tenant')),
    created_at TIMESTAMP    DEFAULT NOW()
);

-- ── ROOMS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.rooms (
    id          SERIAL PRIMARY KEY,
    room_number VARCHAR(20)    UNIQUE NOT NULL,
    type        VARCHAR(20)    NOT NULL DEFAULT 'Single' CHECK (type IN ('Single','Double','Studio','Shared')),
    price       NUMERIC(10,2)  NOT NULL DEFAULT 0.00,
    capacity    INTEGER        NOT NULL DEFAULT 1,
    description TEXT,
    status      VARCHAR(15)    NOT NULL DEFAULT 'available' CHECK (status IN ('available','occupied','maintenance')),
    created_at  TIMESTAMP      DEFAULT NOW()
);

-- ── TENANTS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.tenants (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER REFERENCES boarding.users(id) ON DELETE SET NULL,
    full_name    VARCHAR(150) NOT NULL,
    email        VARCHAR(150),
    phone        VARCHAR(30),
    room_id      INTEGER REFERENCES boarding.rooms(id) ON DELETE SET NULL,
    move_in_date DATE,
    created_at   TIMESTAMP DEFAULT NOW()
);

-- ── BOOKINGS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.bookings (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER REFERENCES boarding.users(id) ON DELETE SET NULL,
    room_id    INTEGER NOT NULL REFERENCES boarding.rooms(id) ON DELETE CASCADE,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(150),
    phone      VARCHAR(30),
    message    TEXT,
    status     VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ── BILLS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.bills (
    id          SERIAL PRIMARY KEY,
    tenant_id   INTEGER NOT NULL REFERENCES boarding.tenants(id) ON DELETE CASCADE,
    amount      NUMERIC(10,2) NOT NULL,
    description VARCHAR(255)  NOT NULL,
    due_date    DATE          NOT NULL,
    status      VARCHAR(10)   NOT NULL DEFAULT 'unpaid' CHECK (status IN ('unpaid','paid')),
    paid_date   DATE,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ── MESSAGES ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boarding.messages (
    id           SERIAL PRIMARY KEY,
    sender_id    INTEGER NOT NULL REFERENCES boarding.users(id) ON DELETE CASCADE,
    receiver_id  INTEGER NOT NULL REFERENCES boarding.users(id) ON DELETE CASCADE,
    subject      VARCHAR(255) NOT NULL,
    message      TEXT NOT NULL,
    is_read      BOOLEAN DEFAULT FALSE,
    created_at   TIMESTAMP DEFAULT NOW()
);

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON boarding.messages(receiver_id);
CREATE INDEX IF NOT EXISTS idx_messages_sender ON boarding.messages(sender_id);

-- ── DEFAULT ADMIN ─────────────────────────────────────────────
INSERT INTO boarding.users (username, password, role)
VALUES ('admin', '$2y$10$XZZzj5y.h7WTPmB3P2TH2eKjyPG3nGQTTrV9r56P6aSVOBEG5gQOi', 'admin')
ON CONFLICT (username) DO NOTHING;

-- ── SAMPLE ROOMS ──────────────────────────────────────────────
INSERT INTO boarding.rooms (room_number, type, price, capacity, description, status) VALUES
('101', 'Single', 3500.00, 1, 'Ground floor, near bathroom, with electric fan', 'available'),
('102', 'Single', 3500.00, 1, 'Ground floor, window view', 'available'),
('201', 'Double', 5500.00, 2, 'Second floor, air-conditioned, with cabinet', 'available'),
('202', 'Studio', 7000.00, 2, 'Second floor, private bathroom, fully furnished', 'available'),
('203', 'Shared', 2000.00, 4, 'Shared room with 3 others, lockers provided', 'available'),
('301', 'Single', 4000.00, 1, 'Top floor, great airflow, with study table', 'maintenance')
ON CONFLICT (room_number) DO NOTHING;