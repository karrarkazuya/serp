from odoo import models, fields, exceptions
from zk import ZK, const
from datetime import datetime
from datetime import timedelta
from pytz import timezone

class fingerprint_devices(models.Model):
    _name = 'jtemployees.fd'
    _description = 'Fingerprint Devices'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name        = fields.Char(string='Title', required=True, tracking=True)
    ip          = fields.Char(string='IP', required=True, tracking=True)
    port        = fields.Integer(string='Port', default = 4370, required=True, tracking=True)
    enabled     = fields.Boolean(string='Enabled', default = False, required=True, tracking=True)
    frameware   = fields.Char(string='Firmware Version')
    last_fetch  = fields.Datetime(string='Last Fetch')
    status      = fields.Char(string='Status')
                    
    def fetch_attendance(self):
        self.status = 'Offline' 
        conn = False
        try:
            print("connecting..")
            zk = ZK(self.ip, port=self.port, timeout=5)
            conn = zk.connect()
            self.frameware = str(conn.get_firmware_version())
            
            self.status = 'Online' 
            
            days_ago = fields.Datetime.now() - timedelta(days=5)
            print("getting attendance.. " + str(self.ip))
            attendances = conn.get_attendance()
            print("received attendance")
            for attendance in attendances:
                if int(attendance.user_id) <= 0:
                    continue
                
                local_tz = timezone('Asia/Baghdad')
                timestamp = fields.Datetime.to_datetime(attendance.timestamp)
                
                if timestamp < days_ago:
                    continue

                timestamp = local_tz.localize(timestamp).astimezone(timezone('UTC')).replace(tzinfo=None)
                
                print(timestamp)
                
                is_added = self.env['jtemployees.fd.lograw'].sudo().search([('user_id', '=', attendance.user_id), ('timestamp', '=', timestamp)], limit=1)
                
                if not is_added:
                    self.env['jtemployees.fd.lograw'].sudo().create({
                        'user_id': attendance.user_id,
                        'timestamp': timestamp,
                        'device': self.name
                    })
            conn.disconnect()
            conn = False
            self.last_fetch = fields.Datetime.now()
        except Exception as e:
            self.status ="Process terminated: {}".format(e)
        finally:
            if conn:
                conn.disconnect()
                    
    def fetch_attendance_of_devices(self):
        devices = self.env['jtemployees.fd'].sudo().search([('enabled', '=', True)])
        for device in devices:
            device.fetch_attendance()
            
        date_7_days_ago = datetime.now() - timedelta(days=7)
        users = self.env['jtemployees.fd.lograw'].sudo().search([('timestamp', '>', date_7_days_ago)], order='timestamp asc')
        for item in users:
            self.env['jtemployees.fd.log'].sudo().check_in_out_employee(item.user_id, item.timestamp, item.device)
            