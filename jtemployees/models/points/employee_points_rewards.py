from odoo import models, fields, exceptions, api

class JTEmployeesPointsRewards(models.Model):
    _name = 'jtemployees.points.rewards'
    _description = 'points rewards for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Char(
        string='Title',
        tracking=True
    )
    
    employee_id     = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    type            = fields.Selection([("reward", 'Reward'), ("deduction", 'Deduction')], string='Type', default='reward', required=True, tracking=True)
    points          = fields.Integer(string='Points', required=True, tracking=True)
    deleted         = fields.Boolean(string='Active', default=False, tracking=True)

    

    def create(self, vals):
        employee = self.env['hr.employee'].search([("id", "=", vals['employee_id'])], limit=1)
        points_to_give = vals['points']
        if vals['type'] == 'deduction':
            points_to_give = points_to_give * -1
        employee.write({
                "jt_current_points": employee.jt_current_points + points_to_give
            })
        return super(JTEmployeesPointsRewards, self).create(vals)
    
    def write(self, vals):
        if 'deleted' not in vals or 'points' in vals or 'employee_id' in vals or 'type' in vals:
            raise exceptions.ValidationError("Reward points are not modifiable")
        super(JTEmployeesPointsRewards, self).write(vals)
    
    def unlink(self):
        for record in self:
            record.write({"deleted":True})
            points_to_give = record.points
            if record.type == 'deduction':
                points_to_give = points_to_give * -1
            record.employee_id.write({
                    "jt_current_points": record.employee_id.jt_current_points - points_to_give
                })

        return True
