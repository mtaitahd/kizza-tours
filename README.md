# Kizza Tours & Safaris

Premium East Africa Tourism Platform.

## Page Management - SEO Field Examples

### Meta Fields Guidelines

| Field | Max Length | Notes |
|---|---|---|
| **Meta Title** | 60 chars | Keyword mwanzoni, brand mwishoni |
| **Meta Keywords** | — | 5-10 keywords, comma separated |
| **Meta Description** | 150-160 chars | Natural sentence, call to action |

### Examples

#### Page: Sustainable Tourism

| Field | Example |
|---|---|
| **Meta Title** | `Sustainable Tourism in Tanzania | Kizza Tours & Safaris` |
| **Meta Keywords** | `sustainable tourism Tanzania, eco-friendly safaris, responsible travel East Africa, green tourism, conservation safaris Tanzania` |
| **Meta Description** | `Discover sustainable tourism with Kizza Tours. Experience eco-friendly safaris in Tanzania, Kenya & Uganda that support conservation and local communities. Book your responsible adventure today.` |

#### Page: Safari Packing List

| Field | Example |
|---|---|
| **Meta Title** | `Ultimate Safari Packing List | What to Bring on an East Africa Safari` |
| **Meta Keywords** | `safari packing list, what to bring on safari, East Africa safari gear, Tanzania safari essentials, Africa travel tips` |
| **Meta Description** | `Complete safari packing list for East Africa. Learn what to bring on your Tanzania, Kenya or Uganda safari. Expert tips from Kizza Tours for a comfortable adventure.` |

## Admin Staff and Activity Log

For an existing database, take a backup and apply `database/user-management.sql` once to the database configured for this project. For a new database, first apply `database/schema.sql`, then `database/user-management.sql`. Apply the migration before deploying or opening the updated admin pages. Confirm that the intended owner account has the `super_admin` role and is the only account with that role; the owner account cannot be changed or deactivated through User Management.

The migration adds account activation and session invalidation fields, permission tables, and the append-only activity log. It preserves the access of existing non-owner admin accounts by granting the permissions for current admin modules. New staff accounts receive only the permissions selected by the owner. Activity history starts when the migration and updated PHP code are in use; it does not assign existing content to a user or fabricate past activity.

For rollback, restore the prior PHP files while leaving the new tables and columns in place. Keep the activity table so recorded audit history is retained.

#### Page: Why Choose Kizza Tours

| Field | Example |
|---|---|
| **Meta Title** | `Why Choose Kizza Tours | Best Tanzania Safari Company` |
| **Meta Keywords** | `why choose Kizza Tours, Tanzania safari company, best East Africa tour operator, trusted safari guide, luxury safari Tanzania` |
| **Meta Description** | `Find out why Kizza Tours is the trusted choice for East Africa safaris. Expert guides, custom itineraries, luxury experiences, and unmatched value since 2015.` |
