from odoo import models, fields, exceptions
from odoo.exceptions import ValidationError
from zk import ZK, const
from datetime import datetime, timedelta
from pytz import timezone
import logging


_logger = logging.getLogger(__name__)

class fingerprint_log(models.Model):
    _name = 'jtemployees.fd.log'
    _description = 'Fingerprint Log'
    _order = 'check_in desc'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    employee_id            = fields.Many2one('hr.employee', string='Employee', tracking=True)
    schedule_id            = fields.Many2one('resource.calendar', string='Working schedule', tracking=True)

    user_id                = fields.Integer(string='User ID')
    check_in               = fields.Datetime(string='Check in', tracking=True)
    check_out              = fields.Datetime(string='Check out', tracking=True)
    checkin_fingerprint    = fields.Char(string='Checkin fingerprint', tracking=True)
    checkout_fingerprint   = fields.Char(string='Checkout fingerprint', tracking=True)
    
    day                         = fields.Date("Check In Out Day", tracking=True)
    ip_address                  = fields.Char("IP Address", tracking=True)
    image                       = fields.Image("Checkin image")
    image_checkout              = fields.Image("Checkout image")
    notes                       = fields.Text("Notes", tracking=True)
    
    location_checked_in         = fields.Many2one('jtemployees.location.areas', string='Location Checked In', tracking=True)
    location_checked_out        = fields.Many2one('jtemployees.location.areas', string='Location Checked Out', tracking=True)
    
    attendance_id               = fields.Many2one('hr.attendance', string='Attendance', tracking=True)

    latitude                    = fields.Float(string='Latitude checkin', help='Enter the latitude of the location', tracking=True)
    longitude                   = fields.Float(string='Longitude checkin', help='Enter the longitude of the location', tracking=True)

    latitude_checkout           = fields.Float(string='Latitude checkout', help='Enter the latitude of the location', tracking=True)
    longitude_checkout          = fields.Float(string='Longitude checkout', help='Enter the longitude of the location', tracking=True)
    
    extra_gps_details           = fields.Text("Notes", tracking=True)
    in_and_out                  = fields.Boolean("In and Out", default=False, tracking=True)
    
    checkin_map_url             = fields.Html(string="Checkin location", compute='_compute_checkin_map_url', sanitize = False)
    checkout_map_url            = fields.Html(string="Checkout location", compute='_compute_checkout_map_url', sanitize = False)
    assigned_checkin_map_url    = fields.Html(string="Assigned Checkin location", compute='_compute_assigned_checkin_map_url', sanitize = False)
    assigned_checkout_map_url   = fields.Html(string="Assigned Checkout location", compute='_compute_assigned_checkout_map_url', sanitize = False)
    
    
    def _compute_checkin_map_url(self):
        for record in self:
            link = '/jt_api/hr/map_pin_view?latitude=' + str(record.latitude) + '&longitude=' + str(record.longitude)
            record.checkin_map_url = f'<iframe src = "{link}" height="200" />'
            
    def _compute_checkout_map_url(self):
        for record in self:
            link = '/jt_api/hr/map_pin_view?latitude=' + str(record.latitude_checkout) + '&longitude=' + str(record.longitude_checkout)
            record.checkout_map_url = f'<iframe src = "{link}" height="200" />'
            
    def _compute_assigned_checkin_map_url(self):
        for record in self:
            link = '/jt_api/hr/area_view?id=%s' % record.location_checked_in.id
            record.assigned_checkin_map_url = f'<iframe src = "{link}" width="100%" height="200" />'
            
    def _compute_assigned_checkout_map_url(self):
        for record in self:
            link = '/jt_api/hr/area_view?id=%s' % record.location_checked_out.id
            record.assigned_checkout_map_url = f'<iframe src = "{link}" width="100%" height="200" />'
            

    def write(self, values):
        #print("### writing " + str(values))
        result = super().write(values)
        for record in self:
            if record.employee_id and record.check_in and record.check_out:
                
                try:
                    if not record.attendance_id:
                        # Check for existing attendance
                        existing_attendance = self.env['hr.attendance'].search([
                            ('employee_id', '=', record.employee_id.id),
                            ('check_in', '>=', record.check_in.replace(hour=0, minute=0, second=0)),
                            ('check_in', '<=', record.check_in.replace(hour=23, minute=59, second=59)),
                            ('check_out', '>=', record.check_out.replace(hour=0, minute=0, second=0)),
                            ('check_out', '<=', record.check_out.replace(hour=23, minute=59, second=59)),
                        ], limit=1)
                        
                        if existing_attendance:
                            #print(f"### Attendance already exists for user {record.user_id}")
                            record.attendance_id = existing_attendance.id
                        else:
                            schedule_id = record.schedule_id.id if record.schedule_id else record.employee_id.resource_calendar_id.id
                            
                            payload = {
                                "check_in": record.check_in,
                                "check_out": record.check_out,
                                "employee_id": record.employee_id.id,
                                "jt_work_schedule": schedule_id
                            }
                            
                            #print(f"### Creating attendance: {payload}")
                            attendance_id = self.env['hr.attendance'].sudo().create(payload)
                            record.attendance_id = attendance_id.id
                    else:
                        if "check_in" in values or "check_out" in values:
                            if record.attendance_id.check_in != record.check_in or record.attendance_id.check_out  != record.check_out:
                                record.attendance_id.write({
                                    "check_in": record.check_in,
                                    "check_out": record.check_out,
                                })
                                
                except ValidationError as e:
                    # Skip validation errors and log them
                    print(f"Skipping attendance creation/update for {record.employee_id.name}: {str(e)}")
                    continue
                        
                except Exception as e:
                    _logger.error(f"Error creating attendance: {str(e)}", exc_info=True)
                    #print(f"### Error creating attendance payload: {str(values)}")
                    #print(f"### Error creating attendance payload user: {str(self.user_id)}")
                    raise
            
            if not record.day:
                record.day = datetime.now(timezone("Asia/Baghdad")).strftime('%Y-%m-%d')
        
        return result
    
    # to check if the new finger print is going to be shiftting
    def is_four_hours_old(self, source_hours: float, timestamp: datetime) -> bool:
        # if the timestamp is 1 hours old, then we can not depend on getting the working schedule..
        # 1 hour because working schedules change in 1 hours, anything more will make issues
        now = datetime.now()
        if (now - timestamp) > timedelta(hours=1):
            return False
        
        source_datetime = now.replace(hour=0, minute=0, second=0, microsecond=0) + timedelta(hours=source_hours)
        if source_datetime > timestamp:
            return (source_datetime - timestamp) < timedelta(hours=4)
        return (timestamp - source_datetime) < timedelta(hours=4)
    
    
    def is_four_hours_closer(self, start_time: float, end_time: float, timestamp: datetime, action) -> bool:
        
        return True
        dt_hour = timestamp.hour
        dt_minute = timestamp.minute

        dt_minutes = (dt_hour * 60) + dt_minute
        start_minutes = round(start_time * 60)
        end_minutes = round(end_time * 60)

        diff_start = abs(dt_minutes - start_minutes)
        diff_end = abs(dt_minutes - end_minutes)

        # handle crossing midnight
        diff_start = min(diff_start, 1440 - diff_start)
        diff_end = min(diff_end, 1440 - diff_end)
        
        can_checkin = False
        can_checkout = False
        
        if diff_start <= 240 and diff_end <= 240:
            if diff_start < diff_end:
                can_checkin = True
            elif diff_start > diff_end:
                can_checkout = True
            else:
                return True
        elif diff_start <= 240:
            can_checkin = True
        elif diff_end <= 240:
            can_checkout = True
            
        if action == 'checkin':
            return can_checkin
    
        if action == 'checkout':
            return can_checkout
            
        return False
    
    
    def check_in_out_employee(self, finger_user_id, timestamp, device_name):
        employee = self.env['hr.employee'].sudo().search([('jt_fingerprint_id', '=', int(finger_user_id))], limit=1)
        
        is_duplicate = self.env['jtemployees.fd.log'].sudo().search([
            ('user_id', '=', finger_user_id),
            '|',
            ('check_in', '=', timestamp),
            ('check_out', '=', timestamp),
        ], order='id desc', limit=1)
        if is_duplicate:
            return False
        
        latest_self = self.env['jtemployees.fd.log'].sudo().search([
            ('user_id', '=', finger_user_id)
        ], order='id desc', limit=1)
        
        if not latest_self:
            #print("#######1")
            if employee:
                if not employee.resource_calendar_id.flexible_hours and self.is_four_hours_old(employee.resource_calendar_id.jt_end_time, timestamp):
                    return False
            logged = self.env['jtemployees.fd.log'].sudo().create({
                "user_id": finger_user_id,
                "check_in": timestamp,
                "checkin_fingerprint": device_name
            })
            if employee:
                logged.employee_id = employee.id
                logged.schedule_id = employee.resource_calendar_id.id
            return logged
        else:
            # if duplicate we pass
            if latest_self.check_in == timestamp or latest_self.check_out == timestamp:
                return False
            if not latest_self.check_out:
                d1 = latest_self.check_in
                d2 = timestamp
                difference_in_minutes = (d2 - d1).total_seconds() / 60
                if difference_in_minutes > (60 * employee.company_id.jt_period_between_sessions): # we ignore if more than 20 hours
                    #print("#######2")
                    if employee:
                        if not employee.resource_calendar_id.flexible_hours and self.is_four_hours_old(employee.resource_calendar_id.jt_end_time, timestamp):
                            return False
                        
                    logged = self.env['jtemployees.fd.log'].sudo().create({
                        "user_id": finger_user_id,
                        "check_in": timestamp,
                        "checkin_fingerprint": device_name
                    })
                    if employee:
                        logged.employee_id = employee.id
                        logged.schedule_id = employee.resource_calendar_id.id
                    return logged
                elif difference_in_minutes < 60 and difference_in_minutes > 15:
                    return False
                else:
                    if difference_in_minutes > 15:
                        latest_self.check_out = timestamp
                        latest_self.checkout_fingerprint = device_name
                        return latest_self
            else:
                d1 = latest_self.check_out
                d2 = timestamp
                difference_in_minutes = (d2 - d1).total_seconds() / 60
                if difference_in_minutes > (60 * 4): # we sign new check in only if passed 4 hours for the last checkout
                    #print("#######3")
                    if employee:
                        if not employee.resource_calendar_id.flexible_hours and self.is_four_hours_old(employee.resource_calendar_id.jt_end_time, timestamp):
                            return False
                        
                    logged = self.env['jtemployees.fd.log'].sudo().create({
                        "user_id": finger_user_id,
                        "check_in": timestamp,
                        "checkin_fingerprint": device_name
                    })
                    if employee:
                        logged.employee_id = employee.id
                        logged.schedule_id = employee.resource_calendar_id.id
                    return logged
                
            # check if already has check_in and check_out
            latest_self = self.env['jtemployees.fd.log'].sudo().search([
                    ('user_id', '=', finger_user_id),
                    ('check_out', '!=', False),
                ], order='check_out desc', limit=1)
            
            if latest_self:
                d1 = latest_self.check_out
                d2 = timestamp
                difference_in_hours = (d2 - d1).total_seconds() / 60 / 60
                if difference_in_hours < 4:
                    return False
            
            # for old records
            # first we get record older without checkout and make sure the period is less than 14 hours
            latest_self = self.env['jtemployees.fd.log'].sudo().search([
                ('user_id', '=', finger_user_id),
                ('check_in', '<', timestamp),
                ('check_out', '=', False),
            ], order='id desc', limit=1)
            
            if latest_self:
                d1 = latest_self.check_in
                d2 = timestamp
                difference_in_hours = (d2 - d1).total_seconds() / 60 / 60
                if difference_in_hours > 14:
                    #print("#######4")
                    if employee:
                        if not employee.resource_calendar_id.flexible_hours and self.is_four_hours_old(employee.resource_calendar_id.jt_end_time, timestamp):
                            return False
                        
                    logged = self.env['jtemployees.fd.log'].sudo().create({
                        "user_id": finger_user_id,
                        "check_in": timestamp,
                        "checkin_fingerprint": device_name
                    })
                    if employee:
                        logged.employee_id = employee.id
                        logged.schedule_id = employee.resource_calendar_id.id
                    return logged
                elif difference_in_hours < 1:
                    return False
                else:
                    latest_self.check_out = timestamp
                    latest_self.checkout_fingerprint = device_name
                    return latest_self
            else:
                if employee:
                    if not employee.resource_calendar_id.flexible_hours and self.is_four_hours_old(employee.resource_calendar_id.jt_end_time, timestamp):
                        return False
                #print("#######5")
                logged = self.env['jtemployees.fd.log'].sudo().create({
                        "user_id": finger_user_id,
                        "check_in": timestamp,
                        "checkin_fingerprint": device_name
                    })
                if employee:
                    logged.employee_id = employee.id
                    logged.schedule_id = employee.resource_calendar_id.id
                return logged
                    
       
        
        
    
