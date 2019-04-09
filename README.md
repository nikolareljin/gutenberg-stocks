# Stock Information
Demo project for Zac Gordon's JS for WP course : https://javascriptforwp.com

Plugin renders Stock index information as a Time Series of the global equity specified, covering 20+ years of historical data.

__Requirements:__ Gutenberg 4.8 (or later).

The data is shown for a period of the last month (30 days) till the current day.

![alt text](.github/screenshots/stock_info_display.png?raw=true "Configure settings for the Stock Information plugin")

This plugin uses __Alphavantage API__ service for historical stock data retrieval:
https://www.alphavantage.co/

__Note :__ Alphavantage API allows 4 requests per minute in the Free API service so using more than that number of requests will result in an error.  


Please get your API token before activating the plugin. 
https://www.alphavantage.co/support/#api-key

 

## Setup application
Clone the project into your Wordpress `wp-content/plugins` or `wp-content/mu-plugins` .

In the administration section of your website, Activate the plugin.

Configure your API settings in the plugin congfiguration.

### Configure the plugin

Open the page configuration page and change the default settings.

Go to the following link: __*wp-admin/admin.php?page=stockinfo_settings*__

![alt text](.github/screenshots/configuration.png?raw=true "Configure settings for the Stock Information plugin")

Please insert the Token you previously retrieved from the API service:
https://www.alphavantage.co/support/#api-key


## Use the plugin
Insert new Block into the content.
![alt text](.github/screenshots/insert.png?raw=true "Insert Stock Info plugin into the post")

Search the Stock Index key you would like to show.

The plugin is using Autocomplete functionality to search for the indexes using Alphavantage API. To start the autocomplete, you need to type `$` character that will enable the Autocompleter.

![alt text](.github/screenshots/enter_search.png?raw=true "Search for the stock index")

Select the index and click Enter.
![alt text](.github/screenshots/pick_index_3.png?raw=true "Select index you would like to show")

Final result with the stock graph displayed in the post.
You can change the Index once it was displayed on the page by pressing `$` in the `Enter Comppany name to find Stock Index` prompt.

![alt text](.github/screenshots/show_index.png?raw=true "Select index you would like to show")

## Prepare PHP

`composer install --no-dev`

to install the required packages

`composer dump-autoload --optimize`

## More information about the API

https://www.alphavantage.co/documentation/


## Compile React components

Install NPM dependencies

`npm install`

## 👉  `npm start`
- Use to compile and run the block in development mode.
- Watches for any changes and reports back any errors in your code.

## 👉  `npm run build`
- Use to build production code for your block inside `dist` folder.
- Runs once and reports back the gzip file sizes of the produced code.

## 👉  `npm run eject`
- Use to eject your plugin out of `create-guten-block`.
- Provides all the configurations so you can customize the project as you want.
- It's a one-way street, `eject` and you have to maintain everything yourself.
- You don't normally have to `eject` a project because by ejecting you lose the connection with `create-guten-block` and from there onwards you have to update and maintain all the dependencies on your own.


### React Stock Charts
This project uses React Stock Charts to render the time series: http://rrag.github.io/react-stockcharts/documentation.html#/svg_vs_canvas

More information about the React component: https://github.com/rrag/react-stockcharts


### Create Guten Block 

This project was bootstrapped with [Create Guten Block](https://github.com/ahmadawais/create-guten-block).

Below you will find some information on how to run scripts.

>You can find the most recent version of the Create Guten Block guide [here](https://github.com/ahmadawais/create-guten-block).

