from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta
from pytz import timezone
from odoo.addons.mail.models.mail_thread import MailThread


class ResourceCalendar(models.Model, MailThread):
    _inherit = 'resource.calendar'
    
    
    
    jt_has_saturday      = fields.Boolean('Saturday', tracking=True)
    jt_has_sunday        = fields.Boolean('Sunday', tracking=True)
    jt_has_monday        = fields.Boolean('Monday', tracking=True)
    jt_has_tuesday       = fields.Boolean('Tuesday', tracking=True)
    jt_has_wednesday     = fields.Boolean('Wednesday', tracking=True)
    jt_has_thursday      = fields.Boolean('Thursday', tracking=True)
    jt_has_friday        = fields.Boolean('Friday', tracking=True)
    
    jt_start_time        = fields.Float("Start Time", help="When is the employee starts his/her shift", tracking=True)
    jt_end_time          = fields.Float("End Time", help="When is the employee ends his/her shift", tracking=True)
    
    jt_employee_ids      = fields.One2many('hr.employee', 'resource_calendar_id', string='Employees', tracking=True)
    
    jt_group_id          = fields.Many2one('jtemployees.calendar.groups', string='Group', tracking=True)
    
    
    employee_ids_m2m = fields.Many2many(
        "hr.employee",
        compute="_compute_employee_ids_m2m",
        inverse="_inverse_employee_ids_m2m",
        string="Employees",
    )

    def _compute_employee_ids_m2m(self):
        for rec in self:
            rec.employee_ids_m2m = rec.jt_employee_ids

    def _inverse_employee_ids_m2m(self):
        for rec in self:
            rec.jt_employee_ids.resource_calendar_id = False
            rec.jt_employee_ids = rec.employee_ids_m2m

    def write(self, vals):
        if 'jt_start_time' in vals and vals['jt_start_time'] > 23.99:
            vals['jt_start_time'] = 23.99
        if 'jt_end_time' in vals and vals['jt_end_time'] > 23.99:
            vals['jt_end_time'] = 23.99
            
        if 'jt_start_time' in vals and vals['jt_start_time'] < 0:
            vals['jt_start_time'] = 0
        if 'jt_end_time' in vals and vals['jt_end_time'] < 0:
            vals['jt_end_time'] = 0
        
        result = super().write(vals)

        listen_fields = ['jt_has_saturday', 'jt_has_sunday', 'jt_has_monday', 'jt_has_tuesday', 'jt_has_wednesday', 'jt_has_thursday', 'jt_has_friday', 'jt_start_time', 'jt_end_time']
        for item in listen_fields:
            if item in vals:
                self.attendance_ids.unlink()
                
                payloads = []
                
                if self.jt_start_time == self.jt_end_time:
                    raise exceptions.ValidationError("start time must not match end time")
                
                if self.jt_start_time < self.jt_end_time:
                    payload = {
                        "calendar_id": self.id,
                        "day_period": "morning",
                        "hour_from": self.jt_start_time,
                        "hour_to": self.jt_end_time,
                    }
                    payloads.append(payload)
                if self.jt_end_time < self.jt_start_time:
                    payload1 = {
                        "calendar_id": self.id,
                        "day_period": "morning",
                        "hour_from": self.jt_start_time,
                        "hour_to": "23.99",
                    }
                    
                    payload2 = {
                        "calendar_id": self.id,
                        "day_period": "afternoon",
                        "hour_from": "00.00",
                        "hour_to": self.jt_end_time,
                    }
                    
                    payloads.append(payload1)
                    payloads.append(payload2)
                
                
                for payload in payloads:
                    if self.jt_has_monday:
                        payload["dayofweek"] = "0"
                        payload["name"] = "Monday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_tuesday:
                        payload["dayofweek"] = "1"
                        payload["name"] = "Tuesday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_wednesday:
                        payload["dayofweek"] = "2"
                        payload["name"] = "Wednesday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_thursday:
                        payload["dayofweek"] = "3"
                        payload["name"] = "Thursday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_friday:
                        payload["dayofweek"] = "4"
                        payload["name"] = "Friday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_saturday:
                        payload["dayofweek"] = "5"
                        payload["name"] = "Saturday"
                        self.attendance_ids.create(payload)
                        
                    if self.jt_has_sunday:
                        payload["dayofweek"] = "6"
                        payload["name"] = "Sunday"
                        self.attendance_ids.create(payload)
                break
        
        
        return result
    
    
class ResourceCalendarAttendance(models.Model):
    _inherit = 'resource.calendar.attendance'
    
    
class ResourceCalendarGroups(models.Model):
    _name = 'jtemployees.calendar.groups'
    _description = 'Groups for the calendar'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Char('Name', required=True, tracking=True)
    
    jt_employee_ids      = fields.One2many('hr.employee', 'jt_calendar_group_id', string='Group Ids', tracking=True)

    employee_ids_m2m = fields.Many2many(
        "hr.employee",
        compute="_compute_employee_ids_m2m",
        inverse="_inverse_employee_ids_m2m",
        string="Employees",
    )
    
    def _compute_employee_ids_m2m(self):
        for rec in self:
            rec.employee_ids_m2m = rec.jt_employee_ids

    def _inverse_employee_ids_m2m(self):
        for rec in self:
            rec.jt_employee_ids.jt_calendar_group_id = False
            rec.jt_employee_ids = rec.employee_ids_m2m