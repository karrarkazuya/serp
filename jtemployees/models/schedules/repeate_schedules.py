from odoo import models, fields, exceptions
from datetime import datetime, timedelta

class repeate_schedules(models.Model):
    _name = 'jtemployees.planned.rschedules'
    _description = 'Schedule planned days'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    employee_id                 = fields.Many2one('hr.employee', string='Employee')
    schedule_id                 = fields.Many2one('resource.calendar', string='Working schedule')
    
        