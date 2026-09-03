<?php if ((int)$r['autorise'] === 1): ?>

                            <span class="badge bg-success">
                                ✔
                            </span>

                            <?php else: ?>

                            <span class="badge bg-danger">
                                ✖
                            </span>

                            <?php endif; ?>


06.05 :

CREATE TABLE palmares_trimestre (

    id INT AUTO_INCREMENT PRIMARY KEY,

    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,

    trimestre VARCHAR(10) NOT NULL,

    lang DECIMAL(10,2) DEFAULT 0,
    math DECIMAL(10,2) DEFAULT 0,
    cult DECIMAL(10,2) DEFAULT 0,

    max_lang DECIMAL(10,2) DEFAULT 0,
    max_math DECIMAL(10,2) DEFAULT 0,
    max_cult DECIMAL(10,2) DEFAULT 0,

    max_total DECIMAL(10,2) DEFAULT 0,
    max_percent DECIMAL(10,2) DEFAULT 100,

    total DECIMAL(10,2) DEFAULT 0,
    percent DECIMAL(10,2) DEFAULT 0,

    obs VARCHAR(255) DEFAULT NULL,

    autorise TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

Mises en jour 2026-2027 :
ALTER TABLE `paiement_detail` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `date_created`;

ALTER TABLE `agent` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `dateEmbauche`;
ALTER TABLE `cycle` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `createby`;
ALTER TABLE `classe` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `createdby`;
ALTER TABLE `cours` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `created_at`;
ALTER TABLE `periodes` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `actif`;
ALTER TABLE `cours_ponderations` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `points`;

ALTER TABLE `affectation_prof_classe` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `date_affect`;
ALTER TABLE `presence_agent` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `statut`;
ALTER TABLE `appel_detail` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `remarque`;
ALTER TABLE `cahier_cotes` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `remarque`;
ALTER TABLE `palmares_trimestre` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `autorise`;

ALTER TABLE `annonces` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `dest_id`;
ALTER TABLE `quiz` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `date_limite`;
ALTER TABLE `quiz_question` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `similarity_min`;
ALTER TABLE `quiz_choice` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `sort_order`;
ALTER TABLE `quiz_question_keyword` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `poids`;
ALTER TABLE `quiz_submission` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `statut`;
ALTER TABLE `quiz_answer` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `feedback`;
ALTER TABLE `quiz_ai_log` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `final_score`;
ALTER TABLE `quiz_attachment` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `file_hash`;
ALTER TABLE `quiz_classe` ADD COLUMN `anneeScolaire` VARCHAR(20) DEFAULT NULL AFTER `assigned_at`;

UPDATE `agent` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `cycle` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `classe` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `cours` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `periodes` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `cours_ponderations` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;

UPDATE `affectation_prof_classe` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `presence_agent` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `appel_detail` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `cahier_cotes` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `palmares_trimestre` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;

UPDATE `annonces` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;

UPDATE `quiz` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_question` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_choice` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_question_keyword` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_submission` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_answer` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_ai_log` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_attachment` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;
UPDATE `quiz_classe` SET `anneeScolaire` = '2025-2026' WHERE `anneeScolaire` IS NULL;

CREATE TABLE IF NOT EXISTS `horaire` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`titre` VARCHAR(150) NOT NULL,
`type` ENUM('Cours', 'Interrogation', 'Examen') NOT NULL DEFAULT 'Cours',
`classe_id` INT NOT NULL,
`cours_id` INT NOT NULL,
`prof_id` INT NOT NULL,
`annee_scolaire_id` INT NOT NULL,
`jour_semaine` ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi') NOT NULL,
`date_evenement` DATE NULL COMMENT 'Date précise obligatoire si type = Examen ou Interrogation',
`heure_debut` TIME NOT NULL,
`heure_fin` TIME NOT NULL,
`salle` VARCHAR(50) DEFAULT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`prof_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annee_scolaire`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_classe` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`jour_date` DATE NOT NULL,
`prof_id` INT NOT NULL,
`classe_id` INT NOT NULL,
`cours_id` INT NOT NULL COMMENT 'Correspond aux branches/cours',
`annee_scolaire_id` INT NOT NULL,
`matieres` TEXT NOT NULL COMMENT 'Sujet / Contenu de la leçon dispensée',
`note` TEXT DEFAULT NULL COMMENT 'Remarques ou observations du prof',
`piece_jointe` VARCHAR(255) DEFAULT NULL COMMENT 'Nom du fichier stocké dans uploads/attachement_journal_de_class/',
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`prof_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annee_scolaire`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE journal_classe
ADD COLUMN statut ENUM('en attente', 'valider', 'rejeter') NOT NULL DEFAULT 'en attente';

CREATE TABLE IF NOT EXISTS `cours_resumes` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`titre` VARCHAR(255) NOT NULL,
`description` TEXT NULL,
`prof_id` INT NOT NULL,
`classe_id` INT NOT NULL,
`cours_id` INT NOT NULL,
`anneScolaire` VARCHAR(50) NOT NULL,
`fichier` VARCHAR(255) NOT NULL,
`type_format` ENUM('pdf', 'video', 'audio', 'document') NOT NULL DEFAULT 'document',
`date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cours_chapitres` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`titre` VARCHAR(255) NOT NULL,
`cours_id` INT NOT NULL,
`classe_id` INT NOT NULL,
`prof_id` INT NOT NULL,
`date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cours_lecons` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`chapitre_id` INT NOT NULL,
`titre` VARCHAR(255) NOT NULL,
`description` TEXT NULL,
`fichier` VARCHAR(255) NOT NULL,
`type_format` ENUM('pdf', 'video', 'audio', 'document') NOT NULL DEFAULT 'document',
`date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`chapitre_id`) REFERENCES `cours_chapitres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

===== Mises à jour, 24.08.2026 =====
CREATE TABLE IF NOT EXISTS `fiche_cours` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `prevision_detail_id` INT(11) NOT NULL,
  `prof_id` INT(11) NOT NULL,
  `objectif_operationnel` TEXT DEFAULT NULL,
  `materiel_didactique` TEXT DEFAULT NULL,
  `rappel_prof` TEXT DEFAULT NULL,
  `rappel_eleve` TEXT DEFAULT NULL,
  `motivation_prof` TEXT DEFAULT NULL,
  `motivation_eleve` TEXT DEFAULT NULL,
  `analyse_prof` TEXT DEFAULT NULL,
  `analyse_eleve` TEXT DEFAULT NULL,
  `evaluation_prof` TEXT DEFAULT NULL,
  `evaluation_eleve` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prevision` (`prevision_detail_id`),
  CONSTRAINT `fk_fiche_cours_prevision` FOREIGN KEY (`prevision_detail_id`) REFERENCES `prevision_detail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- En-tête de la prévision
CREATE TABLE IF NOT EXISTS `prevision_matiere` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`enseignant_id` INT NOT NULL,
`cours_id` INT NOT NULL, -- Référence directe vers votre table `cours`
`anneeScolaire` VARCHAR(20) NOT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Détails des leçons (Basé sur vos fiches d'inspection)
CREATE TABLE IF NOT EXISTS `prevision_detail` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`prevision_id` INT NOT NULL,
`periode` VARCHAR(50) NOT NULL, -- ex: 1ÈRE PERIODE
`mois` VARCHAR(30) NOT NULL, -- ex: SEPTEMBRE 2026
`semaine_libelle` VARCHAR(50) NOT NULL, -- ex: Du 1er au 04
`savoirs_essentiels` TEXT NOT NULL, -- Titre / Contenu du cours
`code` VARCHAR(50) DEFAULT NULL, -- Code matière (si applicable)
`date_execution` DATE DEFAULT NULL, -- Savoirs eff. enseignés (Date)
`observation` TEXT NULL,
FOREIGN KEY (`prevision_id`) REFERENCES `prevision_matiere`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `fiche_cours` ADD COLUMN `fichier_joint` VARCHAR(255) DEFAULT NULL AFTER `evaluation_eleve`;

#Mise à jour 26.08.2026
DROP TABLE IF EXISTS `fiche_cours`;

CREATE TABLE `fiche_cours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prevision_detail_id` int(11) NOT NULL,
  `prof_id` int(11) NOT NULL,
  `date_cours` date DEFAULT NULL,
  `domaine` varchar(255) DEFAULT NULL,
  `branche` varchar(255) DEFAULT NULL,
  `sous_branche` varchar(255) DEFAULT NULL,
  `sujet` varchar(255) DEFAULT NULL,
  `matiere` text DEFAULT NULL,
  `objectif_specifique` text DEFAULT NULL,
  `objectif_operationnel` text DEFAULT NULL,
  `strategies` varchar(255) DEFAULT NULL,
  `materiel_didactique` text DEFAULT NULL,
  `prerequis_prof` text DEFAULT NULL,
  `prerequis_eleve` text DEFAULT NULL,
  `prerequis_strat` varchar(255) DEFAULT NULL,
  `prerequis_duree` varchar(50) DEFAULT NULL,
  `motivation_prof` text DEFAULT NULL,
  `motivation_eleve` text DEFAULT NULL,
  `motivation_strat` varchar(255) DEFAULT NULL,
  `motivation_duree` varchar(50) DEFAULT NULL,
  `annonce_prof` text DEFAULT NULL,
  `annonce_eleve` text DEFAULT NULL,
  `annonce_strat` varchar(255) DEFAULT NULL,
  `annonce_duree` varchar(50) DEFAULT NULL,
  `analyse_prof` text DEFAULT NULL,
  `analyse_eleve` text DEFAULT NULL,
  `analyse_strat` varchar(255) DEFAULT NULL,
  `analyse_duree` varchar(50) DEFAULT NULL,
  `synthese_prof` text DEFAULT NULL,
  `synthese_eleve` text DEFAULT NULL,
  `synthese_strat` varchar(255) DEFAULT NULL,
  `synthese_duree` varchar(50) DEFAULT NULL,
  `application_prof` text DEFAULT NULL,
  `application_eleve` text DEFAULT NULL,
  `application_strat` varchar(255) DEFAULT NULL,
  `application_duree` varchar(50) DEFAULT NULL,
  `evaluation_prof` text DEFAULT NULL,
  `evaluation_eleve` text DEFAULT NULL,
  `evaluation_strat` varchar(255) DEFAULT NULL,
  `evaluation_duree` varchar(50) DEFAULT NULL,
  `fichier_joint` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prevision` (`prevision_detail_id`),
  CONSTRAINT `fk_fiche_cours_prevision` FOREIGN KEY (`prevision_detail_id`) REFERENCES `prevision_detail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `prevision_detail` ADD `activites` VARCHAR(20) NOT NULL AFTER `observation`;

#01.09.2026

ALTER TABLE cours_lecons MODIFY fichier VARCHAR(255) NULL;
ALTER TABLE cours_lecons ADD COLUMN contenu LONGTEXT NULL AFTER description;
ALTER TABLE prevision_matiere ADD COLUMN fichier_joint VARCHAR(255) NULL AFTER anneeScolaire;