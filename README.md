# zabbix-dashboard-selector
Frustrated with Zabbix filters? 😩✨ 

Discover a simpler way to manage your host dashboards!

With our easy-to-use host dashboard selector, you can streamline your monitoring experience and regain control. No more hassle with complex filters—just a straightforward solution that puts you back in charge! 🚀💻

## Key Benefits:
- User-Friendly Interface: Navigate effortlessly without "filters" and find what you need quickly! 🖱️
- Customizable Dashboards: Tailor your view to focus on the metrics that matter most to you. 📊
- Enhanced Monitoring: Keep track of your systems without the frustration of complicated setups. 🔍

Take the stress out of monitoring with Zabbix and enjoy a smoother workflow! 🌟

# Steps
1. create your dashboard in all of your templates that you want with your own necesities
2. Create a macro called {$GROUPIDS} and set all groups that do you want to see, 
  ![image](https://github.com/user-attachments/assets/61ed0b8c-74cc-4d98-a76f-d947020aadac)

 `{$GROUPIDS} = 2,4,5,6...` 
 
3. install new module on `/usr/share/zabbix/modules` folder
4. enable module
5. go to menu
6. go to monitoring -> Host Dashboard Selector
   
   ![image](https://github.com/user-attachments/assets/620bc60e-6b50-4506-915c-eb85b59da468)



As you can see, [groupID] - groupName (group_devices_counter).
On each host all problems active with severity and colours and a button to see each dashboard 

### Dashboard URL is -> `zabbix.php?action=host.dashboard.view&hostid=xxxx`

![image](https://github.com/user-attachments/assets/e792758f-1d10-45f2-8353-99ba60581a31)
