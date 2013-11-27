#
#   Script for creating the  Zmax tables
#



CREATE TABLE zmax_lang (lang VARCHAR(10) NOT NULL,
						name VARCHAR(255) NOT NULL,
						PRIMARY KEY (lang) 
);

INSERT INTO zmax_lang (lang, name) VALUES ('de', 'Deutsch');
INSERT INTO zmax_lang (lang, name) VALUES ('en', 'English');
INSERT INTO zmax_lang (lang, name) VALUES ('es', 'Español');
INSERT INTO zmax_lang (lang, name) VALUES ('fr', 'Français');
INSERT INTO zmax_lang (lang, name) VALUES ('it', 'Italiano');
INSERT INTO zmax_lang (lang, name) VALUES ('pt', 'Português');



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


