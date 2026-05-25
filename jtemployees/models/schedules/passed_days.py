from odoo import models, fields, exceptions
from datetime import datetime, timedelta


class jt_emp_passed_days(models.Model):
    _name = 'jtemployees.passed.days'
    _description = 'Schedule passed days'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    date              = fields.Date(string='Date')
    employee_id       = fields.Many2one('hr.employee', string='Employee')
    is_day_off        = fields.Boolean('Is Day Off')
    
    start_time        = fields.Float("Start Time", help="When is the employee starts his/her shift")
    end_time          = fields.Float("End Time", help="When is the employee ends his/her shift")
    
    