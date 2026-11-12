# EdFund website — cPanel deployment guide (no terminal needed)

You only use **cPanel File Manager, MySQL Databases and phpMyAdmin**. Everything is already prepared in this package.

**What you have**

| File | What it is |
| --- | --- |
| `edfund-cpanel-ready.zip` | The whole website (code, pictures, settings template). |
| `edfund-database.sql` | The database: tables plus the current pages, stories and picture list. |

Set aside about 30 minutes. Do the steps in order.

---

## Step 0 — Back up (2 minutes)

If a website already runs on this domain (for example WordPress), take a backup first:
cPanel → **Backup** → *Download a Full Account Backup* (or at least *Home Directory* and *Databases*). Keep it on your computer.

## Step 1 — Choose the PHP version

1. cPanel → **MultiPHP Manager** → tick `edfundfoundation.org` → choose the **newest PHP available (8.3, 8.4 or 8.5)** → **Apply**.
   The site needs a recent security update of PHP (8.3.36, 8.4.27 or 8.5.12 or newer). If your host only offers an older update, ask the host to update PHP; the site deliberately refuses to run on outdated PHP.
2. cPanel → **Select PHP Version** → **Extensions**. Make sure these are ticked: `pdo_mysql`, `mbstring`, `dom` (also shown as *xml*), `fileinfo`, `gd`, `exif`, `sodium`, `openssl`. Save.
3. Same screen → **Options**: set `memory_limit` to **128M** or more, `upload_max_filesize` to **8M** and `post_max_size` to **10M**. Save.

## Step 2 — Create the database

cPanel → **MySQL® Databases**.

1. **Create New Database** → name it `edfund` → *Create*. cPanel adds your account prefix, so the real name looks like `myaccount_edfund`.
2. **MySQL Users → Add New User** → username `edfund`, click *Password Generator*, copy the password somewhere safe → *Create User*. The real user looks like `myaccount_edfund`.
3. **Add User To Database** → choose that user and that database → *Add* → tick **ALL PRIVILEGES** → *Make Changes*.

Write down these three things — you need them in Step 5:
**database name**, **database user**, **database password**.

## Step 3 — Put the data into the database

1. cPanel → **phpMyAdmin**.
2. Click your database name (`myaccount_edfund`) in the left list.
3. Open the **Import** tab → **Choose File** → select `edfund-database.sql` → scroll down → **Import**.
4. You should see a green *"Import has been successfully finished"* message and **18 tables** on the left (they start with `cms_`).

Do this **once only**. (Importing a second time stops with a harmless "table already exists" error — it never erases anything.)

## Step 4 — Upload the website

1. cPanel → **File Manager** → click **Settings** (top right) → tick **Show Hidden Files (dotfiles)** → Save.
2. Open the folder `/home/myaccount` — **the folder that contains `public_html`, not inside it**.
3. If `public_html` already holds an old website, create a folder called `old-site`, select everything inside `public_html` and **Move** it there. (If you did Step 0 you are safe.)
4. Click **Upload**, add `edfund-cpanel-ready.zip`, wait for 100%.
5. Back in File Manager, right-click the zip → **Extract** → extract into `/home/myaccount`.

After extracting you will see three folders next to each other:

```
/home/myaccount/
    public_html/     ← the website everyone sees
    edfund-app/      ← private code (nobody can open this from the internet)
    edfund-data/     ← private settings, pictures you upload, backups
```

> If your domain's folder is not `public_html` (check cPanel → **Domains**), move the *contents* of `public_html` into that folder. Leave `edfund-app` and `edfund-data` where they are.

## Step 5 — Enter your database details (the only file you edit)

1. File Manager → open **`edfund-data`** → right-click **`config.php`** → **Edit**.
2. Change only these three lines (keep the quotation marks and the semicolon):

```php
$DB_NAME     = 'myaccount_edfund';              // your database name from Step 2
$DB_USER     = 'myaccount_edfund';              // your database user from Step 2
$DB_PASSWORD = 'the-password-you-copied';       // your database password from Step 2
$SETUP_KEY   = 'a long private phrase you invent, 12+ characters';
```

3. **Save Changes.**

## Step 6 — Create your login

1. Open **https://edfundfoundation.org/** — the homepage should appear.
2. Open **https://edfundfoundation.org/admin/setup**.
3. Type your **setup key** (the phrase from Step 5), your **name**, and a **password** (twice) → **Create account**.
4. Sign in at **https://edfundfoundation.org/admin/login**.

The setup page switches itself off once your account exists. Anyone visiting it afterwards sees "Page not found".

## Step 7 — Quick check (5 minutes)

- Click through *Home, About Us, Our Work, Our Team, Stories & Updates, Gallery, Volunteer, Contact*.
- Open **https://edfundfoundation.org/sitemap.xml** (a list of pages) and **https://edfundfoundation.org/robots.txt** (it should contain a `Sitemap:` line and must **not** contain `Disallow: /` on its own).
- In the admin, open a page, change a word, press **Save Draft**, then **Publish**, and look at the live page.

## Step 8 — Make Google find the site

1. Go to **Google Search Console** → **Add property**.
   - Easiest: **URL prefix** → `https://edfundfoundation.org/` → choose the **HTML tag** method → copy only the long code inside `content="…"`.
   - Paste it into `edfund-data/config.php` on the line `'google_site_verification' => '',` between the quotes → Save → go back to Search Console and press **Verify**.
   - (Alternatively use the *Domain* method with a DNS TXT record in cPanel → **Zone Editor**.)
2. Search Console → **Sitemaps** → type `sitemap.xml` → **Submit**.
3. Search Console → **URL Inspection** → paste `https://edfundfoundation.org/` → **Request indexing**. Repeat for *About Us*, *Our Work*, *Stories & Updates* and *Contact*.
4. Optional: do the same at **Bing Webmaster Tools** (use `'bing_site_verification'`).

Google usually lists a new site within a few days to a few weeks. Publishing stories regularly and sharing the site link on the foundation's social pages helps.

## Looking after the site

- **Forgot your password:** edit `edfund-data/config.php`, change `'allow_owner_reset' => false,` to `true`, open `/admin/setup`, enter your setup key and a new password. Then set it back to `false`.
- **Backups:** cPanel → **Backup** (the home directory includes `edfund-data`, where uploaded pictures live) and phpMyAdmin → **Export** for the database. Do this monthly and after big changes.
- **Updating the website later:** upload a newer zip to `/home/myaccount` and extract it, **but never overwrite or delete `edfund-data`**. If an update includes a new `.sql` file, it will say so: import only that file in phpMyAdmin.
- Never share `config.php`, the database password or the setup key.

## If something goes wrong

| What you see | What to do |
| --- | --- |
| "EdFund is temporarily unavailable" | The PHP version/extensions (Step 1) or the database details (Step 5) are wrong. Re-check both. The reason is written in `edfund-data/storage/logs/`. |
| "Invalid request host" | You opened the site through a different address. Use `https://edfundfoundation.org/`. |
| Plain 500 error | Hidden file `.htaccess` missing in `public_html` (enable *Show Hidden Files* and re-extract), or `mod_rewrite` is off — ask your host. |
| `/admin/setup` says "Page not found" | Your setup key is shorter than 12 characters, or an account already exists (go to `/admin/login`). |
| Pictures are missing | Make sure the whole `public_html/assets` folder uploaded. |
| Browser warns about security | Turn on the free SSL certificate in cPanel (**SSL/TLS Status → Run AutoSSL**) and enable **Force HTTPS Redirect** under **Domains**. |

Package version information is in `edfund-app/RELEASE.json`.
