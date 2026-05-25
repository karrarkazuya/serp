from odoo import models, fields, exceptions
import json

class location_areas(models.Model):
    _name = 'jtemployees.location.areas'
    _description = 'Location checkin'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name                        = fields.Char("Area Name", required=True, tracking=True)
    area_data                   = fields.Text('area_data',                tracking=True)
    employee_id                 = fields.Many2one('hr.employee', string='Added by')
    map_url                     = fields.Html(string="Map", compute='_compute_map_url', sanitize = False)
    deleted                     = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    checkin_log_ids             = fields.One2many(
                                    'jtemployees.fd.log',
                                    'location_checked_in',
                                    string='Check-in Logs'
                                )

    checkout_log_ids            = fields.One2many(
                                    'jtemployees.fd.log',
                                    'location_checked_out',
                                    string='Check-out Logs'
                                )
    
    checkin_employees_ids       = fields.One2many(
                                    'hr.employee',
                                    'jt_location_area',
                                    string='Current Check-in Employees'
                                )
    
    checkout_employees_ids      = fields.One2many(
                                    'hr.employee',
                                    'jt_location_area_checkout',
                                    string='Current Check-out Employees'
                                )
    
    
    
    def create(self, vals_list):
        vals_list['employee_id'] = self.env.user.employee_id.id
        if 'area_data' in vals_list:
            vals_list['area_data'] = str(vals_list['area_data']).replace('\n', '').replace('\\', '').replace(' ', '').replace('\'', '"')
        return super().create(vals_list)
    
    def write(self, values):
        if self.area_data and ((self.checkin_log_ids and len(self.checkin_log_ids.ids) > 0) or (self.checkout_log_ids and len(self.checkout_log_ids.ids) > 0)):
            raise exceptions.ValidationError(f"Changing map location is not allowed after set, create new one if you need to change a location")
        if 'area_data' in values:
            values['area_data'] = str(values['area_data']).replace('\n', '').replace('\\', '').replace(' ', '').replace('\'', '"')
        
        return super().write(values)

    def _compute_map_url(self):
        for record in self:
            link = '/jt_api/hr/area_view?id=%s' % record.id
            record.map_url = f'<iframe src = "{link}" width="100%" height="200" />'
            
    def check_inside_area(self, latitude, longitude):
        for record in self:
            area_data = json.loads(record.area_data)
            area_south = float(area_data['south'])
            area_north = float(area_data['north'])
            area_east  = float(area_data['east'])
            area_west  = float(area_data['west'])
            
            """
            Checks if the given coordinates (lat, lon) are inside the defined area.
            
            :param lat: Latitude of the point to check.
            :param lon: Longitude of the point to check.
            :param area_north: Northern boundary of the area.
            :param area_south: Southern boundary of the area.
            :param area_east: Eastern boundary of the area.
            :param area_west: Western boundary of the area.
            :return: True if inside area, False if outside area.
            """
            return area_south <= latitude <= area_north and area_west <= longitude <= area_east
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True