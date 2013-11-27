#
#   Script for creating the MyReview database.
#   Important: always run this script in an UTF-8 encoded database.
#

#
#   Create the tables  of the Zmax framework
#

CREATE TABLE zmax_lang (lang VARCHAR(10) NOT NULL,
						name VARCHAR(255) NOT NULL,
						PRIMARY KEY (lang) 
);

INSERT INTO zmax_lang (lang, name) VALUES ('en', 'English');
INSERT INTO zmax_lang (lang, name) VALUES ('fr', 'Français');

CREATE TABLE zmax_namespace (namespace VARCHAR(10) NOT NULL,
							 description VARCHAR(255) NOT NULL, 					
							 PRIMARY KEY (namespace)
);

INSERT INTO zmax_namespace (namespace, description)
  VALUES ('def', 'The default namespace. Always loaded with Zmax');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('zmax', 'The namespace for zmax-specific texts. Always loaded with Zmax');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('db', 'The namespace for translation of DB schema-dependent information');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('form', 'The namespace for translation of form buttons and options');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('author', 'The namespace for all actions of the Author controller');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('admin', 'The namespace for all actions of the Admin controller');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('reviewer', 'The namespace for all actions of the Reviewer controller');
INSERT INTO zmax_namespace (namespace, description)
  VALUES ('mail', 'The namespace for all pre-defined mails of MyReview');

CREATE TABLE zmax_text (namespace VARCHAR(20) NOT NULL,
						lang VARCHAR(10) NOT NULL, 					
						text_code VARCHAR(40) NOT NULL,
						the_text TEXT,
						PRIMARY KEY (namespace, lang, text_code),
						FOREIGN KEY (lang) REFERENCES zmax_lang(lang)
);



CREATE TABLE zmax_user (user_id VARCHAR(10) NOT NULL,
						user_fname VARCHAR(40),
						user_lname VARCHAR(40),
						user_email VARCHAR(60),
						user_super_admin CHAR(1) DEFAULT 'N',
						PRIMARY KEY (user_id)
);


#
# List of countries
#

CREATE TABLE Country (code VARCHAR(2) NOT NULL,
                      name VARCHAR(60) NOT NULL,
      PRIMARY KEY (code)
)
ENGINE=InnoDB;


#
# Define the sections of an abstract (a list of sections)
#

CREATE TABLE AbstractSection (
   id INTEGER AUTO_INCREMENT NOT NULL,
   section_name VARCHAR(50) NOT NULL,
   position INT NOT NULL,
   field_height INT NOT NULL, /* Height of the TEXTAREA field */
   mandatory CHAR(1) DEFAULT 'Y', /* Tells whether this section is mandatory */
  PRIMARY KEY (id),
  UNIQUE (section_name)
)
ENGINE=InnoDB;

#
# Default structure: one section 'main'
#

INSERT INTO AbstractSection VALUES (1, 'main', 1, 4, 'Y');

#
# File types
#
CREATE TABLE FileType (extension   VARCHAR(10) DEFAULT 'pdf',
	                    description   VARCHAR(100) NOT NULL, 	        
                       PRIMARY KEY (extension)
                     ) Engine=InnoDB;
INSERT INTO FileType VALUES ('pdf', 'Portable Document Format');
INSERT INTO FileType VALUES ('zip', 'ZIP archive');
INSERT INTO FileType VALUES ('doc', 'Microsfot word');
INSERT INTO FileType VALUES ('txt', 'Text file');

#
# Phases of a submission evaluation (global info, at the conf/journal level)
#

CREATE TABLE Phase (id     INt NOT NULL AUTO_INCREMENT,
	                    description   VARCHAR(100) NOT NULL,
                       PRIMARY KEY (id)
                     ) Engine=InnoDB;
INSERT INTO Phase VALUES (1, 'Submission');
INSERT INTO Phase VALUES (2, 'Reviewing');
INSERT INTO Phase VALUES (3, 'Selection');
INSERT INTO Phase VALUES (4, 'Proceedings');
INSERT INTO Phase VALUES (5, 'Revision');

#
# Files required for a submission at a specific phase
#

CREATE TABLE RequiredFile (id INT NOT NULL AUTO_INCREMENT,
                                id_phase     INt NOT NULL,
	                     file_code    VARCHAR(20) NOT NULL, 
	              file_extension   VARCHAR(10) DEFAULT 'pdf',
			mandatory CHAR(1) DEFAULT 'Y',
    INDEX (id_phase),
                   FOREIGN KEY (id_phase) REFERENCES Phase(id),
           INDEX (file_extension),
                   FOREIGN KEY (file_extension) REFERENCES FileType(extension),
                       PRIMARY KEY (id),
                   UNIQUE (id_phase, file_code, file_extension)
                     ) Engine=InnoDB;

/* Require a PDF file during submission */
INSERT INTO RequiredFile VALUES (1, 1, 'submission', 'pdf', 'Y');

/* Require the slides during proceedings preparation */
INSERT INTO RequiredFile VALUES (2, 4, 'slides', 'pdf', 'Y');

#
# Papers
#

CREATE TABLE Paper  (id INTEGER AUTO_INCREMENT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    authors VARCHAR(255) NOT NULL,
                    emailContact  VARCHAR (60) NOT NULL,
                    abstract      TEXT NOT NULL,
	                  topic INTEGER,
                     nb_authors_in_form INT DEFAULT 4,
                    status INTEGER DEFAULT 1, /* Gives the current status of a paper */
                    isUploaded CHAR(1) DEFAULT 'N',
                    format   VARCHAR(10) DEFAULT 'pdf',
                    fileSize INTEGER,
                    submission_date INT,
                    assignmentWeight INTEGER,
	            inCurrentSelection CHAR(1) DEFAULT 'Y' NOT NULL,
		    	CR INTEGER NOT NULL DEFAULT 1,
                    id_conf_session INT, 
                    position_in_session INT, 
                    PRIMARY KEY (id))
ENGINE=InnoDB;

#  ALTER TABLE `Paper` CHANGE `status` `status` INT NULL DEFAULT '1' 

#
# Submission workflow: indicates the position of a submission in the evaluation process
#

CREATE TABLE PaperWorkflow (id     INt NOT NULL AUTO_INCREMENT,
	                    description_code   VARCHAR(100) NOT NULL,
                       PRIMARY KEY (id)
                     ) Engine=InnoDB;
INSERT INTO PaperWorkflow VALUES (1, 'in_submission');
INSERT INTO PaperWorkflow VALUES (2, 'in_evaluation');
INSERT INTO PaperWorkflow VALUES (3, 'in_revision');
INSERT INTO PaperWorkflow VALUES (4, 'final_decision');

#
# List of files uploaded with a submission
#

CREATE TABLE Upload (id_paper INT NOT NULL,
                      id_file INT NOT NULL,
                      file_size INT NOT NULL,
                      upload_date DATETIME NOT NULL,
                    PRIMARY KEY (id_paper, id_file),
          FOREIGN KEY (id_paper) REFERENCES Paper(id),
         INDEX (id_file),
         FOREIGN KEY (id_file) REFERENCES RequiredFile(id)
) ENGINE=InnoDB;
 
#
# Store abstracts in a dependent table
#

CREATE TABLE Abstract (
    id_paper INT NOT NULL,
   id_section INTEGER NOT NULL,
    content TEXT,
  PRIMARY KEY (id_paper, id_section),
  FOREIGN KEY (id_paper) REFERENCES Paper(id),
INDEX (id_section),
  FOREIGN KEY (id_section) REFERENCES AbstractSection(id)
);

#
# Users of the system: can be author, reviewer, admin, etc.
#

CREATE TABLE `User` (id INTEGER AUTO_INCREMENT NOT NULL,
                     gender CHAR(1),
                       lang VARCHAR(10) DEFAULT 'en',
                         last_name VARCHAR (30) NOT NULL ,
                         first_name VARCHAR (30) NOT NULL,
			email VARCHAR (60) NOT NULL,
                         `password` VARCHAR(60) NOT NULL,
                          address VARCHAR(256), 
                        city  VARCHAR (60),
		       zip_code   VARCHAR (30) ,
                        phone VARCHAR(20),
                       fax VARCHAR(20),
                     state  VARCHAR (30),
                      country_code  VARCHAR (2),
                         affiliation VARCHAR(100),
                         roles    VARCHAR(10) DEFAULT 'A' NOT NULL,
                    invitation_confirmed CHAR(1) DEFAULT 'N', /* for reviewers */
                       requirements TEXT, /* for registrations */
                       cv TEXT, 
                   creation_date DATE,
                    payment_mode INT,
                  payment_received CHAR(1),
                         PRIMARY KEY (id),
                   UNIQUE(email))
ENGINE=InnoDB;

# ALTER TABLE User ADD cv TEXT; ALTER TABLE User ADD creation_date DATE;
# ALTER TABLE User ADD payment_mode INT; ALTER TABLE User ADD payment_received CHAR(1);
# ALTER TABLE User ADD lang  VARCHAR(10); ALTER TABLE User ADD gender  CHAR(1);
# ALTER TABLE User ADD invitation_confirmed  CHAR(1) DEFAULT 'N'
# ALTER TABLE User ADD address  VARCHAR(255)

# Add a first admin
INSERT INTO User (email, last_name, first_name, password,  affiliation, roles)
           VALUES ("myreview@lri.fr", "Rigaux", "Philippe", "0c528b25aa6af5028fd2ec2d5b315a80", "LRI", "R,C");
           


#
# Authors of papers: relationship between Paper and User
#
	
CREATE TABLE Author (id_paper INTEGER NOT NULL,
                        id_user INT NOT NULL,
	        	position INTEGER NOT NULL,
                         contact VARCHAR(1),
	                PRIMARY KEY (id_paper, id_user),
                        FOREIGN KEY (id_paper) REFERENCES `Paper`(id),
           INDEX (id_user),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)
)
ENGINE = InnoDB;
	               
#
# Question for papers
#

CREATE TABLE PaperQuestion (id INTEGER NOT NULL AUTO_INCREMENT,
		            question_code   VARCHAR(50) NOT NULL, 
                         PRIMARY KEY (id))
Engine=InnoDB;

CREATE TABLE PQChoice (id_choice  INTEGER NOT NULL AUTO_INCREMENT,
                       choice VARCHAR(40) NOT NULL,
			id_question INTEGER NOT NULL,
                      position INT,
                         PRIMARY KEY (id_choice),
INDEX (id_question),
                        FOREIGN KEY (id_question)  REFERENCES PaperQuestion(id))
Engine=InnoDB;


CREATE TABLE PaperAnswer (id_paper INTEGER NOT NULL,
                           id_question INTEGER NOT NULL,
                           id_answer INTEGER, 
                      PRIMARY KEY (id_paper, id_question),
   INDEX (id_answer),
                      FOREIGN KEY (id_answer) REFERENCES PQChoice(id_choice))
Engine=InnoDB;

#
# One question, for illustration (may be useful anyway)
#

INSERT INTO PaperQuestion (id, question_code)  VALUES (1, 'author_in_committee');
INSERT INTO PQChoice (choice, id_question) VALUES ('No', 1);
INSERT INTO PQChoice (choice, id_question) VALUES ('Yes', 1);
#
# Codes: Paper status, research topics, criterias, 
#

CREATE TABLE PaperStatus (id INTEGER NOT NULL AUTO_INCREMENT,
	                  label   VARCHAR(30) NOT NULL, 
	                  mailTemplate   VARCHAR(40) NOT NULL, 
	      		    cameraReadyRequired CHAR(1) NOT NULL, 
	      		    final_status CHAR(1)  DEFAULT 'N', /* Tells whether this status is final */
		            PRIMARY KEY (id)
                     )
Engine=InnoDB;

# ALTER TABLE PaperStatus ADD final_status CHAR(1)  DEFAULT 'N';

#
# List of non-final status: they define the workflow of a submission
#

INSERT INTO PaperStatus (id, label, mailTemplate, cameraReadyRequired, final_status)
            VALUES (1, 'in_submission', '', 'N', 'N');
INSERT INTO PaperStatus (id, label, mailTemplate, cameraReadyRequired, final_status)
            VALUES (2, 'in_evaluation', '', 'N', 'N');
INSERT INTO PaperStatus (id, label, mailTemplate, cameraReadyRequired, final_status)
            VALUES (3, 'in_author_feedback', '', 'N', 'N');
INSERT INTO PaperStatus (id, label, mailTemplate, cameraReadyRequired, final_status)
            VALUES (4, 'in_revision', '', 'N', 'N');
            
#
# Two default final status: accept and reject
#

INSERT INTO PaperStatus (label, mailTemplate, cameraReadyRequired, final_status)
            VALUES ("reject", "notif_reject", 'N', 'Y');
INSERT INTO PaperStatus (label, mailTemplate, cameraReadyRequired, final_status)
            VALUES ("accept", "notif_accept", 'Y', 'Y');

CREATE TABLE ResearchTopic (id INTEGER NOT NULL AUTO_INCREMENT,
	               label   VARCHAR(100) NOT NULL, 
		       PRIMARY KEY (id)
                     );

INSERT INTO ResearchTopic (label) VALUES ("First topic");
INSERT INTO ResearchTopic (label) VALUES ("Second topic");


CREATE TABLE Criteria (id INTEGER NOT NULL AUTO_INCREMENT,
	               label   VARCHAR(30) NOT NULL, 
	               explanations TEXT,
                       weight INTEGER NOT NULL DEFAULT 0,
		       PRIMARY KEY (id)
                     )
Engine=InnoDB;

INSERT INTO Criteria(label, weight) VALUES ("Originality",0);
INSERT INTO Criteria(label, weight) VALUES ("Quality", 0);
INSERT INTO Criteria(label, weight) VALUES ("Relevance", 0);
INSERT INTO Criteria(label, weight) VALUES ("Presentation", 0);
INSERT INTO Criteria(label, weight) VALUES ("Recommendation", 1);

CREATE TABLE RateLabel (id INTEGER NOT NULL,
	               label   VARCHAR(30) NOT NULL, 
		       PRIMARY KEY (id)
                     );
INSERT INTO RateLabel(id, label) VALUES (0, 'No!!');
INSERT INTO RateLabel(id, label) VALUES (1, 'Better not');
INSERT INTO RateLabel(id, label) VALUES (2, 'Why not');
INSERT INTO RateLabel(id, label) VALUES (3, 'Interested');
INSERT INTO RateLabel(id, label) VALUES (4, 'Eager');

#
# Reviews
#

CREATE TABLE Review (idPaper INTEGER NOT NULL,
	               id_user INT NOT NULL, 
                       overall FLOAT,
                       reviewerExpertise INTEGER,
                       summary TEXT,
                       details TEXT,
                       comments TEXT,
                       fname_ext_reviewer VARCHAR(60), /* External reviewer */
                       lname_ext_reviewer VARCHAR(60), /* last name */
                       submission_date INT, /* for SGBD compatibility */
                       last_revision_date INT, /* Idem */
                       PRIMARY KEY (idPaper, id_user),
                       FOREIGN KEY (idPaper) REFERENCES `Paper`(id),
INDEX (id_user),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)
                     )
Engine=InnoDB;

CREATE TABLE ReviewMark (idPaper INTEGER NOT NULL,
	                 id_user       INT NOT NULL, 
                         idCriteria  INTEGER NOT NULL,
                         mark INTEGER NOT NULL,
                         PRIMARY KEY (idPaper, id_user, idCriteria),
                         FOREIGN KEY (idPaper, id_user) REFERENCES Review(idPaper, id_user),
 INDEX (idCriteria),
                         FOREIGN KEY (idCriteria) REFERENCES Criteria(id))
Engine=InnoDB;

#
# Question for reviews
#

CREATE TABLE ReviewQuestion (id INTEGER NOT NULL AUTO_INCREMENT,
		            question_code   VARCHAR(255) NOT NULL, 
                            public  CHAR(1) NOT NULL DEFAULT 'Y',
                         PRIMARY KEY (id))
Engine=InnoDB;

CREATE TABLE RQChoice (id_choice  INTEGER NOT NULL AUTO_INCREMENT,
                       choice VARCHAR(40) NOT NULL,
			id_question INTEGER NOT NULL,
                      position INT,
                         PRIMARY KEY (id_choice),
INDEX(id_question),
                        FOREIGN KEY (id_question)
                               REFERENCES ReviewQuestion(id))
Engine=InnoDB;

CREATE TABLE ReviewAnswer (id_paper INTEGER NOT NULL,
			   id_user INT NOT NULL,
                           id_question INTEGER NOT NULL,
                           id_answer INTEGER, 
                      PRIMARY KEY (id_paper, id_user, id_question),
    INDEX(id_answer),
                      FOREIGN KEY (id_answer) REFERENCES RQChoice(id_choice))
Engine=InnoDB;

#
# One question, for illustration (may be useful anyway)
#

INSERT INTO ReviewQuestion (id, question_code, public) 
 VALUES (1, 'best_paper_award', 'Y');
INSERT INTO RQChoice (choice, id_question) VALUES ('No', 1);
INSERT INTO RQChoice (choice, id_question) VALUES ('Yes', 1);

#
#
# Rating 
#

CREATE TABLE Rating (idPaper INTEGER NOT NULL,
	               id_user      INT NOT NULL, 
                       rate  FLOAT,
                       significance FLOAT DEFAULT 0,
                       PRIMARY KEY (idPaper,id_user),
                       FOREIGN KEY (idPaper) REFERENCES `Paper`(id),
INDEX(id_user),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)
                     )
Engine=InnoDB;

#
# Assignment proposal
#

CREATE TABLE Assignment (idPaper INTEGER NOT NULL,
	               id_user  INT NOT NULL,
                       weight       FLOAT NOT NULL,
                       PRIMARY KEY (idPaper, id_user),
                       FOREIGN KEY (idPaper) REFERENCES Paper(id),
INDEX(id_user),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)
                     )
Engine=InnoDB;

#
# Research topics selected by PC members
#

CREATE TABLE UserTopic (id_user        INT NOT NULL, 
                         id_topic INT NOT NULL, 
                       PRIMARY KEY (id_user, id_topic),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)                     )
Engine=InnoDB;

#
# Papers secondary topics
#

CREATE TABLE PaperTopic (id_paper  INTEGER NOT NULL, 
                         id_topic INTEGER NOT NULL, 
                       PRIMARY KEY (id_paper,id_topic),
                       FOREIGN KEY (id_paper) REFERENCES Paper(id)
                     )
Engine=InnoDB;

#
# Conference slots
#

CREATE TABLE Slot	 (id INT NOT NULL AUTO_INCREMENT,
        	          slot_date DATE NOT NULL,
		          begin TIME, 
                         end TIME, 
                       PRIMARY KEY (id)
                    )
Engine=InnoDB;

#
# Need a view to show the content of a slot in select fields
# --> you need MySQL V5 to create a view

CREATE VIEW ShowSlot AS SELECT id, CONCAT(slot_date, ' ', begin, '-', end) AS slot FROM Slot;

#
# Conference sessions
#

CREATE TABLE ConfSession (id INT NOT NULL AUTO_INCREMENT,
                         id_slot INT NOT NULL,
                         name VARCHAR(100) NOT NULL,
                        room VARCHAR(100),
                         comment VARCHAR(100),
                         chair VARCHAR(100),
                       PRIMARY KEY (id),
INDEX(id_slot),
		       FOREIGN KEY (id_slot) REFERENCES Slot(id)
                    )
Engine=InnoDB;

#
# Configuration table: all the parameters that affect
# the behavior of the site
#

CREATE TABLE Config (confAcronym VARCHAR(20) NOT NULL,
			confName VARCHAR(100) NOT NULL,
			confURL VARCHAR(100) NOT NULL,
  			submissionURL VARCHAR(100) NOT NULL,
                        conf_location VARCHAR(255) NOT NULL, 
                        confMail VARCHAR(60) NOT NULL,
                        chairMail VARCHAR(60) NOT NULL,
                       chair_names VARCHAR(255) NOT NULL,
                        currency VARCHAR(20) NOT NULL DEFAULT 'Eur',
                        paypal_account VARCHAR(90) NOT NULL,
	                blind_review CHAR(1) NOT NULL,
	                multi_topics CHAR(1) NOT NULL,
                        submissionDeadline DATE NOT NULL,
                        reviewDeadline DATE NOT NULL,
			cameraReadyDeadline DATE NOT NULL,
                        isSubmissionOpen CHAR(1) NOT NULL,
                        isReviewingOpen CHAR(1) NOT NULL,
                        isSelectionOpen CHAR(1) NOT NULL,
                        isProceedingsOpen CHAR(1) NOT NULL,
                      max_abstract_size INT DEFAULT 250, /* Max size of an abstract in words */
                        discussion_mode INT NOT NULL,
 			passwordGenerator VARCHAR(10) NOT NULL,
                     assignment_mode INTEGER NOT NULL, /* Either topic based or global */
                     nbReviewersPerItem INTEGER NOT NULL,
                        /* The following indicates whether a copy
                               of mail must be sent to the conf mngt */
			mailOnAbstract CHAR(1) NOT NULL,
			mailOnUpload CHAR(1) NOT NULL,
			mailOnReview CHAR(1) NOT NULL,
                     /* The following attr. define the current 
                       selection criteria for the paper status summary table */
                     papersWithStatus char(3) NOT NULL,
                     papersWithFilter char(1) NOT NULL,
                     papersWithRate   decimal (5,2) NOT NULL,
                     papersWithReviewer varchar(60) NOT NULL,
                     papersWithConflict char(1) NOT NULL, /* A, Y, N */
                     papersWithMissingReview char(1) NOT NULL, /* Y, N, A*/
                     papersWithTopic int NOT NULL,
                     papersWithTitle VARCHAR(30) NOT NULL,
                     papersWithAuthor VARCHAR(30) NOT NULL,
                     papersUploaded char(1) NOT NULL, /* A, Y, N */
                     papersQuestions VARCHAR(255) NOT NULL,
                     reviewsQuestions VARCHAR(255) NOT NULL,
                                 /* Encoding of questions/answers */
			/* Selection criterias for papers.reviewers
				 during assignment */
                     selectedPaperTopic   INTEGER NOT NULL,
                     selectedReviewerTopic   INTEGER NOT NULL,
                     installInfo   VARCHAR(255) NOT NULL,
                     installationDate   DATE NOT NULL,
                     use_megaupload  CHAR(1) DEFAULT 'N',
                     show_selection_form CHAR(1) DEFAULT 'Y',
	             date_format VARCHAR(10) DEFAULT 'F, d, Y',
	               style_name VARCHAR(60),
	                logo_file VARCHAR(60),
	                 two_phases_submission CHAR(1) DEFAULT 'Y',
                       PRIMARY KEY (confAcronym)
                     )
Engine=InnoDB;

# alter table Config add max_abstract_size INT DEFAULT 250
# alter table Config add isProceedingsOpen char(1) DEFAULT 'N'
# alter table Config add isReviewingOpen char(1) DEFAULT 'N'
# alter table Config add isSelectionOpen char(1) DEFAULT 'N'
# alter table Config add assignment_mode INT DEFAULT 2
# alter table Config add submissionURL VARCHAR(100)
# alter table Config add chair_names VARCHAR(255)
# alter table Config add conf_location VARCHAR(255)
# alter table Config add two_phases_submission CHAR(1) DEFAULT 'Y'
# ALTER TABLE Config ADD style_name VARCHAR(60) DEFAULT 'myreview2.css' AFTER date_format ;
# ALTER TABLE Config ADD logo_file VARCHAR(60) AFTER style_name;

# Always insert one and only one line !!!

INSERT INTO Config Values ('MyReview''10' /* conf acronym */,
			'MyReview conf. management system',
                     'http://myreview.sourceforge.net',
                     'http://myreview.lri.fr',
                      'Paris, oct 15-20 2010',
   			   'myreview@lri.fr' /* Conf mail */,
			   'myreview@lri.fr' /* Chair */,
                          'John Doe', /* Char name(s) */
                           'Eur' /* Currency */,
                           'myreview@lri.fr' /* Paypal account */,
	                   'N', /* Blind review */
	                   'Y', /* Multi-topics */
                            NOW() /* Submission deadline */,
                            NOW() /* Review deadline */,
		            NOW() /* CR deadline */,
                             'Y' /* Submission is open */,
                             'N' /* Reviewing is closed */,
                             'Y' /* Selection is open */,
                             'N' /* Proceedings is closed */,
                            250 /* Max words in an abstract */,
                            1 /* Discussion is closed by default */,
   			   'pwd' /* Simple password generator */,
                           '2' /* Global assignment mode */,
                          '3' /* RevPerPaper*/,
                          'Y', 'Y', 'N',
			 '0' /* Status of selected papers*/, 
			'0'  /* Position wrt the rate below*/, 
			'0'  /* rate of reference  */,
                        'All' /* All reviewers */,
                        'A' /* With or without conflict */,
                        'A' /* With or without missing review */,
                        0 /* Any topic */,
                        'Any' /* Any title */,
                        'Any' /* Any author */,
                        'A' /* Uploaded or not */,
                        '' /* Encoding of paper questions /answers */,
                        '' /* Encoding of review questions /answers */,
	                0, 0 /* No selected topic */,
                       'Manual install', NOW(),
		      'N' /* Do not use mega upload */,
                      'Y' /* Initially, show the selection form */,
                     'F, d, Y' /* Default date format */,
                     'myreview2.css', /* Default CSS file */
                     'paris.jpg', /* Default background image */
                      'Y' /* Yes, the submission runs in two phases */
	);

#
# Session management
#

CREATE TABLE Session (idSession     VARCHAR (40) NOT NULL,
	               id_user        INT NOT NULL, 
	               tempsLimite   DECIMAL (10,0) NOT NULL,
		       roles    VARCHAR(10) NOT NULL,
                       PRIMARY KEY (idSession),
                       INDEX(id_user),
                       FOREIGN KEY (id_user) REFERENCES `User`(id)
                     ) Engine=InnoDB;

#
# Question for registration
#

CREATE TABLE PaymentMode (id INTEGER NOT NULL AUTO_INCREMENT,
		          mode   VARCHAR(255) NOT NULL, 
                         PRIMARY KEY (id));
INSERT INTO PaymentMode (id, mode) VALUES ("1", "PayPal");

CREATE TABLE RegQuestion (id INTEGER NOT NULL AUTO_INCREMENT,
                          question_code   VARCHAR(30) NOT NULL, 
                         PRIMARY KEY (id));

CREATE TABLE RegChoice (id_choice  INTEGER NOT NULL AUTO_INCREMENT,
                    choice VARCHAR(80) NOT NULL,
   	             id_question INTEGER NOT NULL,
                      position INT,
                       cost DECIMAL(12,2),
                        PRIMARY KEY (id_choice),
                        FOREIGN KEY (id_question)
                        REFERENCES RegQuestion(id));

# Just an example
INSERT INTO RegQuestion (id, question_code) VALUES (1, 'Conference registration');
INSERT INTO RegChoice (id_choice, choice, id_question, position, cost) 
VALUES (1, 'Member fee', 1, 1, 100.00);
INSERT INTO RegChoice (id_choice, choice, id_question, position, cost) 
VALUES (2, 'Non member fee', 1, 2, 120.00);
INSERT INTO RegChoice (id_choice, choice, id_question, position, cost) 
VALUES (3, 'Student fee', 1, 3, 80.00);

# Choice from attendess

CREATE TABLE RegAnswer (id_user INT NOT NULL,
                           id_question INT NOT NULL,
                           id_answer INT NOT NULL,
                PRIMARY KEY (id_user, id_question),
                FOREIGN KEY (id_user) REFERENCES `User`(id),
                FOREIGN KEY (id_question) REFERENCES RegQuestion(id),
                FOREIGN KEY (id_answer) REFERENCES RegChoice(id_choice));


#
# Messages (discussion on papers)
#

CREATE TABLE Message (id INT AUTO_INCREMENT NOT NULL,
                    id_parent INT DEFAULT 0,
              id_paper INT NOT NULL,
                    id_user INT NOT NULL,
                    message TEXT NOT NULL,
                    date DATETIME,
                    PRIMARY KEY (id),
                    FOREIGN KEY (id_paper) REFERENCES Paper(id),
                    FOREIGN KEY (id_user) REFERENCES `User`(id)
 ) Engine=InnoDB;

