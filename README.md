
A tool for building json specifications for SuiteCRM.

- Use the command line to quickly whip up json specifications for modules, fields and relationships, taking advantage of autocomplete and field cloning.
- Run bulk actions on all json files to quickly apply changes to all of them.
- Build the json specifications into SuiteCRM modules, relationships and field definitions straight into your instance.

### Installation

1. Open Bash
2. cd into your SuiteCRM directory
3. create dev/spec/json folders
4. git clone this repo
5. composer install

Ready!

### Available commands:

##### `php jsonizer new`

The basic command to add new records.

You'll be asked to choose between creating a module, field or relationship.

Fields and relationships will require you to choose existing modules as parent(s), so you should create module records first.

Some of the data you'll be asked to enter has auto-complete, so for example when entering a field name you'll be suggested from a list of all other fields that exist, on any module. If you select a field name that already exists, all data from this field will be copied, so in most cases you can just check it and continue to the next field.

If you enter the details of an existing record you'll be editing it instead of creating a new one.

##### `php jsonizer dump`

Running this command will simply create a human readable dump of all the data.

##### `php jsonizer bulk`

Edit `/src/Command/BulkJsonCommand.php` to run this command and execute the code on all json record.  
Tip: Make sure all your data is backed up before running this command!