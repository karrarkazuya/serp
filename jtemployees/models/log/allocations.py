from odoo import models, fields, exceptions
from datetime import datetime, timedelta

class payrolls(models.Model):
    _name = 'jtemployees.log.allocations'
    _description = 'Allocations Log'
    name                       = fields.Text(string='Title')
    employee_id                = fields.Many2one('hr.employee', string='Employee')
    jt_timeoff                 = fields.Float(string='Timeoff', default=0)
    jt_leavedays               = fields.Float(string='Leave Days', default=0)
    