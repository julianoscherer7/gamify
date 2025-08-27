-- database.sql (versão robusta)
CREATE DATABASE IF NOT EXISTS livros_gamificados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE livros_gamificados;

-- usuários: agora com streak e last_read
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  points INT DEFAULT 0,
  streak INT DEFAULT 0,
  last_read DATE DEFAULT NULL,
  badge VARCHAR(50) DEFAULT 'Novato',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- livros com gênero
CREATE TABLE IF NOT EXISTS books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  genre VARCHAR(60) DEFAULT 'General',
  points INT DEFAULT 10
) ENGINE=InnoDB;

-- marcação de leitura (único par user-book)
CREATE TABLE IF NOT EXISTS user_books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_book(user_id, book_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- conquistas definidas e relacionamentos já obtidos
CREATE TABLE IF NOT EXISTS achievements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) UNIQUE NOT NULL,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(255),
  icon VARCHAR(64) DEFAULT '⭐'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_achievements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  achievement_id INT NOT NULL,
  unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_achievement(user_id, achievement_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- seeds: livros iniciais
INSERT INTO books (title, description, genre, points) VALUES
('Dom Casmurro','Clássico brasileiro de Machado de Assis.','Ficção',15),
('O Pequeno Príncipe','Livro poético e filosófico de Antoine de Saint-Exupéry.','Infantil',10),
('1984','Distopia sobre vigilância e totalitarismo — George Orwell.','Distopia',20),
('O Hobbit','Fantasia de J. R. R. Tolkien.','Fantasia',25),
('A Revolução dos Bichos','Fábula política por George Orwell.','Distopia',12),
('Sapiens','Uma breve história da humanidade — Yuval Noah Harari.','Não-ficção',30),
('O Alquimista','Paulo Coelho — Jornada espiritual.','Ficção',14)
ON DUPLICATE KEY UPDATE title=title;

-- seeds: achievements padrões
INSERT INTO achievements (code,title,description,icon) VALUES
('first_book','Primeiro Livro','Marcar o primeiro livro como lido.','🥇'),
('ten_books','10 Livros','Marcar 10 livros como lidos.','📚'),
('bookworm_30','Streak 30 dias','Ler ao menos 1 livro por dia durante 30 dias (streak).','🔥'),
('genre_master','Mestre de Gênero','Ler 5 livros do mesmo gênero.','🏅'),
('points_1000','1000 Pontos','Acumular 1000 pontos.','🎯')
ON DUPLICATE KEY UPDATE code=code;

-- exemplo: user demo (senha: 123456)
INSERT INTO users (username, email, password, points, badge) VALUES
('demo','demo@exemplo.com','$2y$10$H7Sjd9c9n2v5aC1yYz8p8eG5D6B7g2mWmY8pX8B0l0ZiZC9Q6QKqS',0,'Novato')
ON DUPLICATE KEY UPDATE email=email;
