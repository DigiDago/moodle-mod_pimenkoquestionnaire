The pimenkoquestionnaire module allows you to construct pimenkoquestionnaires (surveys) from a
variety of question type. It was originally based on questionnaire plugin.

--------------------------------------------------------------------------------
To Install:

1. Load the pimenkoquestionnaire module directory into your "mod" subdirectory.
2. Visit your admin page to create all of the necessary data tables.

--------------------------------------------------------------------------------
To Upgrade:

1. Copy all of the files into your 'mod/pimenkoquestionnaire' directory.
2. Visit your admin page. The database will be updated.
3. As part of the update, all existing surveys are assigned as either 'private',
   'public' or 'temmplate'. Surveys assigned to a single pimenkoquestionnaire are set
   to 'private' with the pimenkoquestionnaire's course as the owner. Surveys assigned
   to multiple pimenkoquestionnaires in the same course are set to 'public' with the
   pimenkoquestionnaire's course as the owner. Surveys assigned to multiple
   pimenkoquestionnaires in multiple courses are set to 'public' with the site ID as
   the owner. Surveys that are not deleted but have no associated pimenkoquestionnaires
   are set to 'template' with the site ID as the owner.

--------------------------------------------------------------------------------
Please read the releasenotes.txt file for more info about successive changes
--------------------------------------------------------------------------------

