
# Zabbix Dashboard Selector

Are you frustrated with complex Zabbix filters? 😩 Discover a simpler way to manage your host dashboards! 🚀

Our easy-to-use host dashboard selector streamlines your monitoring experience, putting you back in control. Say goodbye to complicated filters and hello to a straightforward solution that enhances your Zabbix workflow.

## Key Benefits

- **User-Friendly Interface**: Navigate effortlessly and find what you need quickly 🖱️
- **Customizable Dashboards**: Focus on the metrics that matter most to you 📊
- **Enhanced Monitoring**: Keep track of your systems without the frustration of complex setups 🔍

## Installation and Setup

1. Create dashboards in all your desired templates with your specific requirements.

2. Create a macro called `{$GROUPIDS}` and set all groups you want to see:
 ![Macro Setup](https://github.com/user-attachments/assets/61ed0b8c-74cc-4d98-a76f-d947020aadac)

`{$GROUPIDS} = 2,4,5,6...`
3. Create API on zabbix

4. Install the new module in the `/usr/share/zabbix/modules` folder.
5. Edit `config.json` and set variables
   
serverUrl -> `http://localhost:8080`

apiUrl -> `http://localhost:8080/api_jsonrpc.php` 
  
apiToken -> `5648e91....4a89321`
   
6. Enable the module in Zabbix.
   
7. Navigate to Monitoring -> Host Dashboard Selector
   
8. Have fun 😁

![Host Dashboard Selector](https://github.com/user-attachments/assets/620bc60e-6b50-4506-915c-eb85b59da468)

## Features

- Display format: `[groupID] - groupName (group_devices_counter)`
- View all active problems for each host with severity indicators and colors
- Quick access button to view individual host dashboards
- Cool search function 🔎

### Dashboard URL Format

`zabbix.php?action=host.dashboard.view&hostid=xxxx`

![Dashboard Example](https://github.com/user-attachments/assets/e792758f-1d10-45f2-8353-99ba60581a31)

Take the stress out of monitoring with Zabbix and enjoy a smoother workflow! 🌟

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

## Support

If you encounter any problems or have any questions, please open an issue in this repository.




# To Do..
- [ ] Multiple lang.
- [x] dashboard missing on host XXX
- [x] convert variable API hardcoded to macro
- [x] Variable groups by id using Zabbix Macro
- [x] Alerts by severity on each host by group Id
- [x] Create new submenu


## Support

For support telegram me.
- [@maavcrusoe](https://t.me/maavcrusoe)
