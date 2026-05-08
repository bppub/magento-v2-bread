Bread Pay Checkout for Magento 2
=============================

Helping retailers acquire and convert more customers.

Bread Pay Features
----------------------

* Full Funnel. Your shoppers can discover, pre-qualify, and check out from anywhere - your homepage, 
category page, product page, cart, or checkout. 
* Real-Time Decision. Pre-qualification is quick and easy. Let your customers learn 
about their purchase power in seconds without ever leaving your site.

Installation
------------

### Install using Zip archive

1. Download repository as zip file

2. Unzip contents into `app/code/Bread/BreadCheckout` folder

3. Setup files ( For Canada Merchants )

- Copy file `app/code/Bread/BreadCheckout/etc/adminhtml/system.ca.xml` to `app/code/Bread/BreadCheckout/etc/adminhtml/system.xml`
- Copy file `app/code/Bread/BreadCheckout/view/adminhtml/web/js/validation.ca.js` to `app/code/Bread/BreadCheckout/view/adminhtml/web/js/validation.js`


4. Enable module
    ```bash
    bin/magento module:enable Bread_BreadCheckout
    bin/magento setup:upgrade
    bin/magento setup:di:compile
    bin/magento setup:static-content:deploy
    ```

### Install using composer. 

1. Navigate to your Magento 2 root folder

2. Install the Bread Checkout module    
     ```bash
     composer require breadfinance/module-breadcheckout
     ```
3. For Canada Merchants only

- Copy file `app/code/Bread/BreadCheckout/etc/adminhtml/system.ca.xml` to `app/code/Bread/BreadCheckout/etc/adminhtml/system.xml`
- Copy file `app/code/Bread/BreadCheckout/view/adminhtml/web/js/validation.ca.js` to `app/code/Bread/BreadCheckout/view/adminhtml/web/js/validation.js`

4. Enable module
    ```bash
    bin/magento module:enable Bread_BreadCheckout
    bin/magento setup:upgrade
    bin/magento setup:di:compile
    bin/magento setup:static-content:deploy
    ```
    

## Usage instructions:

### For US Merchants
See documentation at https://platform-docs.breadpayments.com/bread-onboarding/docs/magento-2-integration-steps
Contact your Bread Pay representative for login credentials.

### For Canada Merchants
See documentation at https://rbcpayplan.readme.io/rbc-onboarding/docs/magento-2-integration-steps 
Contact your Payplan representative for login credentials.


## Development Environment Setup

This guide sets up a local Magento environment using [markshust/docker-magento](https://github.com/markshust/docker-magento). Refer to the repository for more details, configuration options, and available commands.


### 1. Create Project Directory & Download Docker Template

```bash
mkdir docker-magento
cd docker-magento
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/template | bash
```

### 2. Configure environment and versions
Open the compose.yaml file and confirm the PHP and DB versions matches your target environment.

For PHP 8.2, ensure the following line is present:
```
phpfpm:
  image: markoshust/magento-php:8.2-fpm-4
```

For MySQL, ensure compatible version is used. Magento currently (As of 6/2025) supports:

MySQL 8.0

MySQL 5.7

MariaDB 10.2 – 10.6

### 2a. Trust Internal Root CA (e.g. Netskope) in Container
If you're on a corporate network that intercepts HTTPS traffic (e.g., via Netskope), you may encounter SSL errors like:

```
curl: (60) SSL certificate problem: self-signed certificate in certificate chain
```
To fix this, you must import your organization's root certificate into the container.

* Visit https://github.com in Chrome.

* Click the padlock icon → "Certificate is valid".

* In the Certification Path tab, select the top-level certificate (e.g., ca.alliancedata.goskope.com).

* Click View Certificate → Details → Copy to File.

* Save as Base-64 encoded X.509 (.CER) — name it netskope_root.cer.

* Place the file in the project root.

* Run these commands

```
docker cp <path-to-downloaded-certificate> phpfpm:/usr/local/share/ca-certificates/netskope_root.crt
```
```
docker exec -u 0 -it phpfpm bash
update-ca-certificates
```

* This will copy the cert into the phpfpm container and update the container’s trusted certificate store.

* You should now be able to run bin/download and composer install without SSL errors.


### 3. Download Magento
Download the Magento version you want (replace 2.4.8 with your desired version):

```
bin/download 2.4.8
```

### 4. Set Up Magento Environment
Run the following commands to complete setup and install sample data:

```
bin/setup magento.test
bin/magento sampledata:deploy
bin/magento setup:upgrade
```
Disable Two-Factor Authentication for local development:
```
bin/composer require markshust/magento2-module-disabletwofactorauth
bin/magento module:enable MarkShust_DisableTwoFactorAuth
bin/magento setup:upgrade
```

### 5. Install Bread Magento Extension
Clone the Bread extension into app/code and enable the module:
```
cd src/app/code
mkdir Bread
cd Bread
git clone git@github.com:getbread/magento-v2-bread.git BreadCheckout
cd ../../../../

bin/magento module:enable Bread_BreadCheckout
bin/magento setup:upgrade
bin/magento cache:flush
```

## Resetting Your Local Magento Environment
To completely remove your local Magento installation and start fresh:

### 1. Navigate to the root of your Magento project:

```
cd /path/to/your/magento-docker-dev
```
### 2. Run the cleanup script and delete all files (including hidden ones):

```
bin/removeall
rm -rf .[^.]* * 
```
⚠️ Warning: This will permanently delete all files and directories in the current folder. Make sure you’re in the correct location before running this command.

