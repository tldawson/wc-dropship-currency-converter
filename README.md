# WooCommerce Exchange Rate Manager  

This is a simple plugin that converts product prices from one currency to another, using an exchange rate from a user specified API, fetched once a day.  

### Usage  

**Note: After activation, it's recommended for you to enter the JSON endpoint, JSON key, and fallback rate, before you enable the plugin.**  

By default, this plugin assumes that all products need to have their prices converted. If you need to exclude specific products or categories, enable exclusions and add them.  

- `Enable Plugin`: This checkbox enables or disables the plugin, separately from plugin activation.
- `API URL`: This is a API endpoint that serves exchange rates in JSON format.  For example: [api.exchangeratesapi.io/latest](https://api.exchangeratesapi.io/latest)
- `JSON Key`: This is the list of **case sensitive** JSON keys that will extract the proper information from the JSON API. **The key order is important; if you enter the keys in the wrong order, the fallback rate will be used.** For example, if you use the [example API URL](https://api.exchangeratesapi.io/latest) and you want to convert EUR to USD: `rates, USD`  
- `Fallback Rate`: This is the rate that's used if there's an error with the rate from the JSON API. Example: `1.2`  
- `Enable Exclusions`: This checkbox enables or disables the exclusion of products or product categories when calculating product prices using the exchange rate.  
- `Excluded Products`: This is a list of products that will not have their prices converted. Search for a product using its SKU.  
- `Excluded Categories`: This is a list of categories that will not have their products' prices converted. Search for a category using its name.  

